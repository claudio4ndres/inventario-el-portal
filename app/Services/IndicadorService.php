<?php

namespace App\Services;

use App\Models\Conteo;
use App\Models\Movimiento;
use App\Models\Producto;
use App\Models\Venta;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * HU15: indicadores del tablero (RF8, K7, OE4).
 *
 * Los indicadores no se guardan: se calculan en cada consulta desde las ventas, los
 * movimientos y los conteos, por lo que siempre reflejan los datos del momento (nunca
 * tienen más de 24 horas de antigüedad). Es la misma idea que el stock calculado desde los lotes.
 */
class IndicadorService
{
    /** Período de M1.1, M2 y M3: las últimas 4 semanas. */
    public const SEMANAS = 4;

    /** M1.2 usa 30 días, el mismo período de la pantalla de conteos (cubre el ciclo mensual de la clase C). */
    public const DIAS_EXACTITUD = 30;

    /**
     * Calcula los cuatro indicadores y la serie semanal de quiebres.
     *
     * @return array{desde: CarbonInterface, calculado: CarbonInterface, m11: array, m12: array, m2: array, m3: array}
     */
    public function calcular(): array
    {
        $desde = $this->inicioPeriodo();

        return [
            'desde' => $desde,
            'calculado' => now(),
            'm11' => $this->ventasConDescuentoAutomatico($desde),
            'm12' => ['exactitud' => Conteo::exactitudPeriodo(self::DIAS_EXACTITUD), 'dias' => self::DIAS_EXACTITUD],
            'm2' => $this->quiebresAltaRotacion($desde),
            'm3' => $this->mermaVencimientoSobreVentas($desde),
        ];
    }

    /** Inicio del período: hace 4 semanas completas, contando el día de hoy (28 días). */
    public function inicioPeriodo(): CarbonInterface
    {
        return today()->subDays(self::SEMANAS * 7 - 1);
    }

    /**
     * M1.1 (K1): porcentaje de ventas cuyo stock se descontó automáticamente por lote.
     *
     * Una venta cuenta como "con descuento automático" cuando TODAS sus líneas tienen
     * movimientos SALIDA que suman exactamente la cantidad vendida.
     *
     * @return array{ventas: int, con_descuento: int, porcentaje: ?float}
     */
    public function ventasConDescuentoAutomatico(CarbonInterface $desde): array
    {
        $ventas = Venta::query()->where('fecha_venta', '>=', $desde);
        $total = (clone $ventas)->count();

        $conDescuento = (clone $ventas)->whereDoesntHave('detalles', fn (Builder $q) => $q->whereRaw(
            'detalle_ventas.cantidad <> (select coalesce(sum(m.cantidad), 0) from movimientos m where m.detalle_venta_id = detalle_ventas.id and m.tipo = ?)',
            [Movimiento::SALIDA]
        ))->count();

        return [
            'ventas' => $total,
            'con_descuento' => $conDescuento,
            'porcentaje' => $total > 0 ? round($conDescuento / $total * 100, 1) : null,
        ];
    }

    /**
     * M2 (K3): quiebres de stock de la lista fija de alta rotación (informe, sección 2.5.3).
     *
     * Se cuenta un quiebre por producto y por día (día-producto): un producto marcado como de
     * alta rotación tiene un quiebre en un día si estuvo sin stock en algún momento de ese día,
     * es decir, si amaneció con stock 0 o si una salida (venta, merma o ajuste) lo dejó en 0.
     * Es la misma definición de la planilla manual de la línea base, por lo que ambas mediciones
     * son comparables. Un producto que nunca ha tenido movimientos todavía no tiene stock que
     * medir (por ejemplo, antes de su primera recepción) y no cuenta.
     *
     * @return array{total: int, semanas: Collection<int, object{desde: CarbonInterface, hasta: CarbonInterface, quiebres: int}>, productos: Collection<int, object{nombre: string, quiebres: int}>}
     */
    public function quiebresAltaRotacion(CarbonInterface $desde): array
    {
        $desde = $desde->copy()->startOfDay();
        $hoy = today();
        $productos = Producto::query()->where('alta_rotacion', true)->where('activo', true)->get(['id', 'nombre']);
        $ids = $productos->pluck('id');

        // Stock con que cada producto llega al período: el del último movimiento anterior
        $stockInicial = Movimiento::query()
            ->whereIn('id', Movimiento::query()
                ->selectRaw('max(id)')
                ->whereIn('producto_id', $ids)
                ->where('created_at', '<', $desde)
                ->groupBy('producto_id'))
            ->pluck('stock_resultante', 'producto_id');

        $movimientos = Movimiento::query()
            ->whereIn('producto_id', $ids)
            ->where('created_at', '>=', $desde)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['id', 'producto_id', 'tipo', 'stock_resultante', 'created_at'])
            ->groupBy(fn (Movimiento $m) => $m->producto_id.'|'.$m->created_at->toDateString());

        // Días con quiebre de cada producto, recorriendo el período día a día
        $dias = collect();
        foreach ($productos as $producto) {
            $stock = $stockInicial->has($producto->id) ? (int) $stockInicial->get($producto->id) : null;   // null: aún sin movimientos
            for ($dia = $desde->copy(); $dia->lte($hoy); $dia->addDay()) {
                $delDia = $movimientos->get($producto->id.'|'.$dia->toDateString(), collect());
                $amanecioSinStock = $stock === 0;
                $quedoEnCero = $delDia->contains(fn (Movimiento $m) => $m->tipo !== Movimiento::ENTRADA && (int) $m->stock_resultante === 0);
                if ($amanecioSinStock || $quedoEnCero) {
                    $dias->push((object) ['producto_id' => $producto->id, 'dia' => $dia->copy()]);
                }
                if ($delDia->isNotEmpty()) {
                    $stock = (int) $delDia->last()->stock_resultante;
                }
            }
        }

        // Serie para el gráfico: una barra por semana, de la más antigua a la actual
        $semanas = collect(range(0, self::SEMANAS - 1))->map(function (int $i) use ($desde, $dias) {
            $inicio = $desde->copy()->addDays($i * 7);
            $fin = $inicio->copy()->addDays(7);

            return (object) [
                'desde' => $inicio,
                'hasta' => $fin->copy()->subDay(),
                'quiebres' => $dias->filter(fn (object $d) => $d->dia->gte($inicio) && $d->dia->lt($fin))->count(),
            ];
        });

        $nombres = $productos->pluck('nombre', 'id');
        $porProducto = $dias->groupBy('producto_id')
            ->map(fn (Collection $grupo, int $id) => (object) ['nombre' => $nombres[$id], 'quiebres' => $grupo->count()])
            ->sortByDesc('quiebres')
            ->values();

        return ['total' => $dias->count(), 'semanas' => $semanas, 'productos' => $porProducto];
    }

    /**
     * M3 (K7): costo de la merma por vencimiento ÷ monto vendido en el período, en %.
     *
     * @return array{merma: int, ventas: int, porcentaje: ?float}
     */
    public function mermaVencimientoSobreVentas(CarbonInterface $desde): array
    {
        $merma = (int) Movimiento::query()
            ->where('tipo', Movimiento::MERMA)
            ->where('motivo_merma', 'vencimiento')
            ->where('created_at', '>=', $desde)
            ->sum('costo_total');

        $ventas = (int) Venta::query()->where('fecha_venta', '>=', $desde)->sum('total');

        return [
            'merma' => $merma,
            'ventas' => $ventas,
            'porcentaje' => $ventas > 0 ? round($merma / $ventas * 100, 1) : null,
        ];
    }
}
