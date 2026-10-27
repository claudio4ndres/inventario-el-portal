<?php

namespace App\Services;

use App\Models\Conteo;
use App\Models\Movimiento;
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

    /** Clase ABC que se considera de alta rotación para el indicador de quiebres (M2). */
    public const CLASE_ALTA_ROTACION = 'A';

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
     * M2 (K3): quiebres de stock de los productos de alta rotación (clase A).
     *
     * Un quiebre es un movimiento de salida (venta, merma o ajuste) que deja el stock del
     * producto en 0. Solo el último lote consumido queda con stock resultante 0, por lo que
     * una venta que toma varios lotes cuenta una sola vez.
     *
     * @return array{total: int, semanas: Collection<int, object{desde: CarbonInterface, hasta: CarbonInterface, quiebres: int}>, productos: Collection<int, object{nombre: string, quiebres: int}>}
     */
    public function quiebresAltaRotacion(CarbonInterface $desde): array
    {
        $quiebres = Movimiento::query()
            ->with('producto:id,nombre')
            ->where('tipo', '<>', Movimiento::ENTRADA)
            ->where('stock_resultante', 0)
            ->where('created_at', '>=', $desde)
            ->whereHas('producto', fn (Builder $q) => $q->where('clase_abc', self::CLASE_ALTA_ROTACION))
            ->get(['id', 'producto_id', 'created_at']);

        // Serie para el gráfico: una barra por semana, de la más antigua a la actual
        $semanas = collect(range(0, self::SEMANAS - 1))->map(function (int $i) use ($desde, $quiebres) {
            $inicio = $desde->copy()->addDays($i * 7);
            $fin = $inicio->copy()->addDays(7);

            return (object) [
                'desde' => $inicio,
                'hasta' => $fin->copy()->subDay(),
                'quiebres' => $quiebres->filter(fn (Movimiento $m) => $m->created_at->gte($inicio) && $m->created_at->lt($fin))->count(),
            ];
        });

        $productos = $quiebres->groupBy('producto_id')
            ->map(fn (Collection $grupo) => (object) ['nombre' => $grupo->first()->producto->nombre, 'quiebres' => $grupo->count()])
            ->sortByDesc('quiebres')
            ->values();

        return ['total' => $quiebres->count(), 'semanas' => $semanas, 'productos' => $productos];
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
