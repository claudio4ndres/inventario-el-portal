<?php

namespace App\Services;

use App\Exceptions\SaldoLoteInsuficienteException;
use App\Exceptions\StockInsuficienteException;
use App\Models\DetalleVenta;
use App\Models\Lote;
use App\Models\Movimiento;
use App\Models\Producto;
use App\Models\Recepcion;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Reglas de negocio del inventario (controles K1, K2 y K5).
 *
 * Toda operación que mueve stock se ejecuta dentro de una transacción:
 * o se registran la venta/recepción, los lotes y la bitácora completos, o no se registra nada.
 */
class InventarioService
{
    /**
     * HU03 / K2: registra una recepción. Cada línea crea un lote y un movimiento ENTRADA.
     *
     * @param  array{proveedor_id:int, numero_documento:string}  $cabecera
     * @param  array<int, array{producto_id:int, cantidad:int, codigo_lote:string, fecha_vencimiento:?string, costo_unitario:?int}>  $lineas
     */
    public function registrarRecepcion(User $usuario, array $cabecera, array $lineas): Recepcion
    {
        return DB::transaction(function () use ($usuario, $cabecera, $lineas) {
            $recepcion = Recepcion::create([
                'proveedor_id' => $cabecera['proveedor_id'],
                'user_id' => $usuario->id,
                'numero_documento' => $cabecera['numero_documento'],
                'fecha_recepcion' => now(),
            ]);

            foreach ($lineas as $linea) {
                $lote = Lote::create([
                    'producto_id' => $linea['producto_id'],
                    'recepcion_id' => $recepcion->id,
                    'codigo_lote' => $linea['codigo_lote'],
                    'fecha_vencimiento' => $linea['fecha_vencimiento'] ?? null,
                    'cantidad_inicial' => $linea['cantidad'],
                    'cantidad_disponible' => $linea['cantidad'],
                    'costo_unitario' => $linea['costo_unitario'] ?? 0,
                ]);

                Movimiento::create([
                    'producto_id' => $lote->producto_id,
                    'lote_id' => $lote->id,
                    'user_id' => $usuario->id,
                    'tipo' => Movimiento::ENTRADA,
                    'cantidad' => $lote->cantidad_inicial,
                    'stock_resultante' => $this->stockDe($lote->producto_id),
                    'recepcion_id' => $recepcion->id,
                    'motivo' => 'Recepción doc. '.$recepcion->numero_documento,
                ]);
            }

            return $recepcion;
        });
    }

    /**
     * HU04 / K1: registra una venta y descuenta el stock lote por lote.
     *
     * Orden de salida: primero el lote que vence antes (los no perecibles al final)
     * y, a igual vencimiento, el que ingresó primero. Es la variante FEFO del criterio
     * FIFO, la recomendada para perecibles (Decreto 977, art. 106).
     *
     * El precio se toma SIEMPRE de la base de datos, nunca del formulario.
     *
     * @param  array<int, array{producto_id:int, cantidad:int}>  $items
     *
     * @throws StockInsuficienteException si algún producto no alcanza (no se registra nada)
     */
    public function registrarVenta(User $usuario, array $items, string $medioPago = 'efectivo'): Venta
    {
        $items = $this->agruparItems($items);

        return DB::transaction(function () use ($usuario, $items, $medioPago) {
            $venta = Venta::create([
                'user_id' => $usuario->id,
                'total' => 0,
                'medio_pago' => $medioPago,
                'fecha_venta' => now(),
            ]);

            $total = 0;

            foreach ($items as $productoId => $cantidad) {
                $producto = Producto::query()->where('activo', true)->findOrFail($productoId);

                // lockForUpdate: en MySQL bloquea los lotes hasta el commit, evitando que dos
                // cajas vendan la misma unidad al mismo tiempo (condición de carrera).
                $lotes = Lote::query()
                    ->where('producto_id', $productoId)
                    ->where('cantidad_disponible', '>', 0)
                    ->orderByRaw('fecha_vencimiento IS NULL')   // no perecibles al final
                    ->orderBy('fecha_vencimiento')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                $disponible = (int) $lotes->sum('cantidad_disponible');
                if ($disponible < $cantidad) {
                    // La excepción revierte la transacción completa (K5: no se permite stock negativo)
                    throw new StockInsuficienteException($producto, $cantidad, $disponible);
                }

                $subtotal = $producto->precio_venta * $cantidad;
                $detalle = DetalleVenta::create([
                    'venta_id' => $venta->id,
                    'producto_id' => $producto->id,
                    'cantidad' => $cantidad,
                    'precio_unitario' => $producto->precio_venta,
                    'subtotal' => $subtotal,
                ]);
                $total += $subtotal;

                $pendiente = $cantidad;
                $stockRestante = $disponible;
                foreach ($lotes as $lote) {
                    if ($pendiente === 0) {
                        break;
                    }
                    $tomar = min($pendiente, $lote->cantidad_disponible);
                    $lote->decrement('cantidad_disponible', $tomar);
                    $pendiente -= $tomar;
                    $stockRestante -= $tomar;

                    Movimiento::create([
                        'producto_id' => $producto->id,
                        'lote_id' => $lote->id,
                        'user_id' => $usuario->id,
                        'tipo' => Movimiento::SALIDA,
                        'cantidad' => $tomar,
                        'stock_resultante' => $stockRestante,
                        'detalle_venta_id' => $detalle->id,
                        'motivo' => 'Venta N.º '.$venta->id,
                    ]);
                }
            }

            $venta->update(['total' => $total]);

            return $venta->load('detalles.producto');
        });
    }

    /**
     * HU13 / K7: registra una merma de un lote (vencimiento, daño, robo u otro).
     *
     * Descuenta el saldo del lote y deja un movimiento MERMA con su costo
     * (cantidad × costo unitario del lote), todo en la misma transacción.
     *
     * @throws SaldoLoteInsuficienteException si la cantidad supera el saldo del lote (no se registra nada)
     */
    public function registrarMerma(User $usuario, int $loteId, int $cantidad, string $motivo, ?string $observacion = null): Movimiento
    {
        if (! array_key_exists($motivo, Movimiento::MOTIVOS_MERMA)) {
            throw new InvalidArgumentException("Motivo de merma no válido: {$motivo}");
        }

        return DB::transaction(function () use ($usuario, $loteId, $cantidad, $motivo, $observacion) {
            // lockForUpdate: impide que una venta simultánea consuma el mismo saldo
            // entre la verificación y el descuento.
            $lote = Lote::query()->lockForUpdate()->findOrFail($loteId);

            if ($cantidad < 1 || $cantidad > $lote->cantidad_disponible) {
                // K5: nunca saldo negativo; la excepción revierte la transacción
                throw new SaldoLoteInsuficienteException($lote, $cantidad);
            }

            $lote->decrement('cantidad_disponible', $cantidad);

            $detalle = 'Merma por '.mb_strtolower(Movimiento::MOTIVOS_MERMA[$motivo]);
            $observacion = trim((string) $observacion);
            if ($observacion !== '') {
                $detalle .= ': '.$observacion;
            }

            return Movimiento::create([
                'producto_id' => $lote->producto_id,
                'lote_id' => $lote->id,
                'user_id' => $usuario->id,
                'tipo' => Movimiento::MERMA,
                'cantidad' => $cantidad,
                'stock_resultante' => $this->stockDe($lote->producto_id),
                'costo_total' => $cantidad * $lote->costo_unitario,
                'motivo_merma' => $motivo,
                'motivo' => Str::limit($detalle, 117),   // la columna admite 120 caracteres
            ]);
        });
    }

    public function stockDe(int $productoId): int
    {
        return (int) Lote::where('producto_id', $productoId)->sum('cantidad_disponible');
    }

    /**
     * Si el mismo producto se escanea dos veces, se suma en una sola línea.
     *
     * @param  array<int, array{producto_id:int, cantidad:int}>  $items
     * @return array<int, int> producto_id => cantidad
     */
    private function agruparItems(array $items): array
    {
        $agrupados = [];
        foreach ($items as $item) {
            $id = (int) $item['producto_id'];
            $agrupados[$id] = ($agrupados[$id] ?? 0) + (int) $item['cantidad'];
        }

        return $agrupados;
    }
}
