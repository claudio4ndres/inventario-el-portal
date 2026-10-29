<?php

namespace App\Services;

use App\Models\DetalleVenta;
use App\Models\Producto;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * HU17 / K6-K7: clasificación ABC automática por valor vendido (RF8, OE4).
 *
 * Se ordenan los productos por el valor vendido (suma de subtotales en CLP) en los
 * últimos 90 días, de mayor a menor, y se acumula su participación:
 *   A: hasta el 80 % acumulado, B: hasta el 95 % y C: el resto (incluye los sin ventas).
 *
 * Un producto pertenece a la clase en que *empieza* su aporte: si el acumulado antes de
 * sumarlo es menor que el 80 %, es A aunque con él se pase del umbral. Así el producto que
 * cruza la frontera queda en la clase más vigilada y, si uno solo concentra más del 80 %
 * de las ventas, igualmente es A.
 *
 * No mueve stock (solo actualiza un parámetro del producto), por eso vive fuera de
 * InventarioService; aun así, todos los productos se actualizan en una sola transacción.
 */
class ClasificacionAbcService
{
    /** Período de ventas considerado: aproximadamente un trimestre. */
    public const DIAS_PERIODO = 90;

    /** Límites del porcentaje acumulado para las clases A y B. */
    public const LIMITE_A = 80;

    public const LIMITE_B = 95;

    /**
     * Recalcula la clase ABC de todos los productos del catálogo.
     * Si no hubo ventas en el período no se modifica nada: sin datos no se puede
     * clasificar y dejar todo en C borraría la clasificación vigente.
     *
     * @return array<string, int>|null productos por clase ['A' => n, 'B' => n, 'C' => n], o null si no hubo ventas
     */
    public function recalcular(): ?array
    {
        $valores = $this->valorVendidoEnPeriodo();

        if ($valores->isEmpty()) {
            return null;
        }

        $clases = self::clasificar($valores->all());

        return DB::transaction(function () use ($clases) {
            $resumen = ['A' => 0, 'B' => 0, 'C' => 0];

            foreach (Producto::query()->orderBy('id')->get() as $producto) {
                $clase = $clases[$producto->id] ?? 'C';
                $resumen[$clase]++;

                if ($producto->clase_abc !== $clase) {
                    $producto->update(['clase_abc' => $clase]);
                }
            }

            return $resumen;
        });
    }

    /**
     * Asigna la clase según el porcentaje acumulado, con aritmética entera
     * (montos en CLP) para no depender de redondeos de punto flotante.
     *
     * @param  array<int, int>  $valores  producto_id => valor vendido (> 0)
     * @return array<int, string> producto_id => 'A' | 'B' | 'C'
     */
    public static function clasificar(array $valores): array
    {
        // Mayor valor primero; ante empate, el id menor, para que el resultado sea estable.
        uksort($valores, fn (int $a, int $b) => [$valores[$b], $a] <=> [$valores[$a], $b]);

        $total = array_sum($valores);
        $acumulado = 0;
        $clases = [];

        foreach ($valores as $productoId => $valor) {
            // acumulado / total < límite / 100  ⇔  acumulado × 100 < límite × total
            $clases[$productoId] = match (true) {
                $acumulado * 100 < self::LIMITE_A * $total => 'A',
                $acumulado * 100 < self::LIMITE_B * $total => 'B',
                default => 'C',
            };
            $acumulado += $valor;
        }

        return $clases;
    }

    /**
     * Valor vendido (CLP) por producto en los últimos 90 días.
     *
     * @return Collection<int, int> producto_id => valor
     */
    private function valorVendidoEnPeriodo(): Collection
    {
        return DetalleVenta::query()
            ->join('ventas', 'ventas.id', '=', 'detalle_ventas.venta_id')
            ->where('ventas.fecha_venta', '>=', now()->subDays(self::DIAS_PERIODO))
            ->groupBy('detalle_ventas.producto_id')
            ->selectRaw('detalle_ventas.producto_id as producto_id, sum(detalle_ventas.subtotal) as valor')
            ->pluck('valor', 'producto_id')
            ->map(fn ($valor) => (int) $valor)
            ->filter(fn (int $valor) => $valor > 0);
    }
}
