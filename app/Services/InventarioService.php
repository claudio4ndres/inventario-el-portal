<?php

namespace App\Services;

use App\Exceptions\SaldoLoteInsuficienteException;
use App\Exceptions\StockInsuficienteException;
use App\Models\Conteo;
use App\Models\DetalleConteo;
use App\Models\DetalleVenta;
use App\Models\Lote;
use App\Models\Movimiento;
use App\Models\OrdenCompra;
use App\Models\Producto;
use App\Models\Recepcion;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
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
     * HU11: si la recepción se asocia a una orden de compra, la OC debe estar enviada y ser
     * del mismo proveedor; al registrar la recepción la OC pasa a "recibida".
     *
     * @param  array{proveedor_id:int, numero_documento:string, orden_compra_id?:?int}  $cabecera
     * @param  array<int, array{producto_id:int, cantidad:int, codigo_lote:string, fecha_vencimiento:?string, costo_unitario:?int}>  $lineas
     *
     * @throws InvalidArgumentException si la OC no está enviada o es de otro proveedor (no se registra nada)
     */
    public function registrarRecepcion(User $usuario, array $cabecera, array $lineas): Recepcion
    {
        return DB::transaction(function () use ($usuario, $cabecera, $lineas) {
            $orden = null;
            if (! empty($cabecera['orden_compra_id'])) {
                // lockForUpdate: dos recepciones simultáneas no pueden cerrar la misma OC
                $orden = OrdenCompra::query()->lockForUpdate()->findOrFail($cabecera['orden_compra_id']);
                if ($orden->estado !== OrdenCompra::ENVIADA || (int) $orden->proveedor_id !== (int) $cabecera['proveedor_id']) {
                    throw new InvalidArgumentException("La orden de compra N.º {$orden->id} debe estar enviada y ser del mismo proveedor.");
                }
            }

            $recepcion = Recepcion::create([
                'proveedor_id' => $cabecera['proveedor_id'],
                'orden_compra_id' => $orden?->id,
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

            $orden?->update(['estado' => OrdenCompra::RECIBIDA, 'fecha_recepcion' => now()]);

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

    /**
     * HU14 / K6: registra el conteo físico de productos de una clase ABC y ajusta las diferencias.
     *
     * Para cada producto compara lo contado con el stock del sistema (suma de sus lotes):
     * - faltante (diferencia < 0): descuenta de los lotes en orden FEFO, como una venta;
     * - sobrante (diferencia > 0): suma al lote ingresado más recientemente.
     * Cada lote afectado deja un movimiento AJUSTE con el motivo indicado. Todo ocurre en una
     * transacción: si una línea es inválida no se registra ni el conteo ni ningún ajuste.
     *
     * @param  array<int, array{cantidad:int, motivo?:?string}>  $lineas  producto_id => conteo físico
     *
     * @throws ValidationException si falta el motivo de una diferencia, un producto no es de la clase
     *                             o hay un sobrante en un producto sin lotes (no se registra nada)
     */
    public function registrarConteo(User $usuario, string $clase, array $lineas): Conteo
    {
        if (! array_key_exists($clase, Conteo::FRECUENCIA_DIAS)) {
            throw ValidationException::withMessages(['clase_abc' => "Clase ABC no válida: {$clase}."]);
        }
        if ($lineas === []) {
            throw ValidationException::withMessages(['conteos' => 'Debe contar al menos un producto.']);
        }

        return DB::transaction(function () use ($usuario, $clase, $lineas) {
            $conteo = Conteo::create([
                'clase_abc' => $clase,
                'user_id' => $usuario->id,
                'fecha_conteo' => now(),
            ]);

            $conDiferencia = 0;

            foreach ($lineas as $productoId => $linea) {
                $productoId = (int) $productoId;
                $producto = Producto::query()->where('activo', true)->where('clase_abc', $clase)->find($productoId);
                if ($producto === null) {
                    throw ValidationException::withMessages([
                        "conteos.{$productoId}.cantidad" => "El producto N.º {$productoId} no es un producto activo de la clase {$clase}.",
                    ]);
                }

                // lockForUpdate: ninguna venta o merma puede mover estos lotes mientras se ajustan
                $lotes = Lote::query()
                    ->where('producto_id', $productoId)
                    ->orderByRaw('fecha_vencimiento IS NULL')   // FEFO: no perecibles al final
                    ->orderBy('fecha_vencimiento')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                $stockSistema = (int) $lotes->sum('cantidad_disponible');
                $contada = (int) $linea['cantidad'];
                $diferencia = $contada - $stockSistema;
                $motivo = trim((string) ($linea['motivo'] ?? ''));

                if ($diferencia !== 0 && $motivo === '') {
                    throw ValidationException::withMessages([
                        "conteos.{$productoId}.motivo" => "Indique el motivo de la diferencia en «{$producto->nombre}» (sistema {$stockSistema}, contado {$contada}).",
                    ]);
                }
                if ($diferencia > 0 && $lotes->isEmpty()) {
                    throw ValidationException::withMessages([
                        "conteos.{$productoId}.cantidad" => "«{$producto->nombre}» no tiene lotes: registre primero una recepción para ingresar ese stock.",
                    ]);
                }

                $detalle = DetalleConteo::create([
                    'conteo_id' => $conteo->id,
                    'producto_id' => $productoId,
                    'stock_sistema' => $stockSistema,
                    'cantidad_contada' => $contada,
                    'diferencia' => $diferencia,
                    'motivo' => $diferencia !== 0 ? $motivo : null,
                ]);

                if ($diferencia === 0) {
                    continue;
                }
                $conDiferencia++;

                $texto = Str::limit(sprintf('Ajuste conteo N.º %d (%s%d): %s', $conteo->id, $diferencia > 0 ? '+' : '−', abs($diferencia), $motivo), 117);
                $ajuste = fn (Lote $lote, int $cantidad, int $stockResultante) => Movimiento::create([
                    'producto_id' => $productoId,
                    'lote_id' => $lote->id,
                    'user_id' => $usuario->id,
                    'tipo' => Movimiento::AJUSTE,
                    'cantidad' => $cantidad,
                    'stock_resultante' => $stockResultante,
                    'detalle_conteo_id' => $detalle->id,
                    'motivo' => $texto,
                ]);

                if ($diferencia > 0) {
                    // Sobrante: se suma al lote más reciente, que es el que probablemente quedó mal recibido
                    $lote = $lotes->sortByDesc('id')->first();
                    $lote->increment('cantidad_disponible', $diferencia);
                    $ajuste($lote, $diferencia, $contada);

                    continue;
                }

                // Faltante: se descuenta lote por lote en orden FEFO; el stock nunca queda negativo
                $pendiente = -$diferencia;
                $stockRestante = $stockSistema;
                foreach ($lotes->where('cantidad_disponible', '>', 0) as $lote) {
                    if ($pendiente === 0) {
                        break;
                    }
                    $tomar = min($pendiente, $lote->cantidad_disponible);
                    $lote->decrement('cantidad_disponible', $tomar);
                    $pendiente -= $tomar;
                    $stockRestante -= $tomar;
                    $ajuste($lote, $tomar, $stockRestante);
                }
            }

            $conteo->update([
                'productos_contados' => count($lineas),
                'productos_con_diferencia' => $conDiferencia,
            ]);

            return $conteo;
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
