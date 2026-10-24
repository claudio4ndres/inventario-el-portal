<?php

namespace App\Services;

use App\Models\DetalleVenta;
use App\Models\Producto;
use Illuminate\Support\Facades\DB;

/**
 * HU10 / K3: cálculo automático del punto de reorden (RF4, OE2).
 *
 *   punto de reorden = demanda diaria promedio × días de reposición + stock de seguridad
 *   demanda diaria promedio = unidades vendidas en los últimos 28 días ÷ 28
 *
 * No mueve stock (solo actualiza un parámetro del producto), por eso vive fuera de
 * InventarioService; aun así, todos los productos se actualizan en una sola transacción.
 */
class PuntoReordenService
{
    /** Período de demanda: 4 semanas completas, para que cada día de la semana pese lo mismo. */
    public const DIAS_PERIODO = 28;

    /**
     * Recalcula el punto de reorden de los productos con al menos una venta en el período.
     * Los productos sin ventas conservan su valor (no se dejan en 0 por falta de datos).
     *
     * @return int cantidad de productos recalculados
     */
    public function recalcular(): int
    {
        $vendidas = $this->unidadesVendidasEnPeriodo();

        if ($vendidas->isEmpty()) {
            return 0;
        }

        return DB::transaction(function () use ($vendidas) {
            $productos = Producto::query()->whereIn('id', $vendidas->keys())->get();

            foreach ($productos as $producto) {
                $producto->update([
                    'punto_reorden' => self::calcular(
                        (int) $vendidas[$producto->id],
                        $producto->dias_reposicion,
                        $producto->stock_seguridad,
                    ),
                ]);
            }

            return $productos->count();
        });
    }

    /**
     * Aplica la fórmula con aritmética entera y redondeo hacia arriba:
     * ceil(vendidas × días de reposición ÷ 28) + stock de seguridad.
     * Redondear hacia arriba evita quedar bajo la demanda real (es preferible
     * reponer una unidad antes que tener un quiebre de stock).
     */
    public static function calcular(int $unidadesVendidas, int $diasReposicion, int $stockSeguridad): int
    {
        $demandaEnReposicion = intdiv($unidadesVendidas * $diasReposicion + self::DIAS_PERIODO - 1, self::DIAS_PERIODO);

        return $demandaEnReposicion + $stockSeguridad;
    }

    /**
     * Unidades vendidas por producto en los últimos 28 días.
     *
     * @return \Illuminate\Support\Collection<int, int> producto_id => unidades
     */
    private function unidadesVendidasEnPeriodo()
    {
        return DetalleVenta::query()
            ->join('ventas', 'ventas.id', '=', 'detalle_ventas.venta_id')
            ->where('ventas.fecha_venta', '>=', now()->subDays(self::DIAS_PERIODO))
            ->groupBy('detalle_ventas.producto_id')
            ->selectRaw('detalle_ventas.producto_id as producto_id, sum(detalle_ventas.cantidad) as unidades')
            ->pluck('unidades', 'producto_id')
            ->filter(fn ($unidades) => (int) $unidades > 0);
    }
}
