<?php

namespace App\Services;

use App\Models\DetalleOrdenCompra;
use App\Models\OrdenCompra;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * HU11: orden de compra sugerida (RF4/RF3, K3/K2, OE2).
 *
 * A partir de las alertas de reposición (HU06) propone cuánto comprar y a quién:
 * - cantidad sugerida = punto de reorden × 2 − stock (llevar el stock al doble del mínimo);
 * - proveedor = el de la recepción más reciente del producto.
 *
 * No mueve stock: el stock solo cambia cuando la mercadería llega y se registra la recepción
 * (InventarioService::registrarRecepcion).
 */
class OrdenCompraService
{
    /** Fórmula de la HU11. Como el producto está en alerta (stock ≤ punto de reorden), el resultado es ≥ 1. */
    public static function cantidadSugerida(int $puntoReorden, int $stock): int
    {
        return max($puntoReorden * 2 - max($stock, 0), 1);
    }

    /**
     * Productos en alerta que todavía no están en una OC abierta, con su cantidad sugerida
     * y su último proveedor (null si nunca se ha recibido o si ese proveedor está inactivo).
     *
     * @return Collection<int, Producto> cada producto trae "stock", "cantidad_sugerida" y "proveedor_sugerido"
     */
    public function sugerencias(): Collection
    {
        $productos = Producto::bajoPuntoReorden()
            ->sinOrdenAbierta()
            ->conStock()
            ->conUltimoProveedor()
            ->orderBy('clase_abc')
            ->orderBy('nombre')
            ->get();

        $proveedores = Proveedor::where('activo', true)
            ->whereIn('id', $productos->pluck('ultimo_proveedor_id')->filter()->unique())
            ->get()
            ->keyBy('id');

        return $productos->each(function (Producto $producto) use ($proveedores) {
            $producto->stock = (int) $producto->stock;
            $producto->cantidad_sugerida = self::cantidadSugerida($producto->punto_reorden, $producto->stock);
            $producto->proveedor_sugerido = $proveedores->get((int) $producto->ultimo_proveedor_id);
        });
    }

    /**
     * Genera una OC en borrador por proveedor con las sugerencias vigentes.
     * Los productos sin proveedor activo no se incluyen (se informan en la pantalla).
     *
     * @return Collection<int, OrdenCompra> órdenes creadas (vacía si no había nada que sugerir)
     */
    public function generarSugeridas(User $usuario): Collection
    {
        return DB::transaction(function () use ($usuario) {
            $porProveedor = $this->sugerencias()
                ->filter(fn (Producto $p) => $p->proveedor_sugerido !== null)
                ->groupBy(fn (Producto $p) => $p->proveedor_sugerido->id);

            return $porProveedor->map(function (Collection $productos, int $proveedorId) use ($usuario) {
                $orden = OrdenCompra::create([
                    'proveedor_id' => $proveedorId,
                    'user_id' => $usuario->id,
                    'estado' => OrdenCompra::BORRADOR,
                ]);

                foreach ($productos as $producto) {
                    DetalleOrdenCompra::create([
                        'orden_compra_id' => $orden->id,
                        'producto_id' => $producto->id,
                        'cantidad_sugerida' => $producto->cantidad_sugerida,
                        'cantidad' => $producto->cantidad_sugerida,   // editable mientras sea borrador
                    ]);
                }

                return $orden;
            })->values();
        });
    }
}
