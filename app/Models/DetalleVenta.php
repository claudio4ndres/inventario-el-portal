<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DetalleVenta extends Model
{
    protected $table = 'detalle_ventas';

    protected $fillable = ['venta_id', 'producto_id', 'cantidad', 'precio_unitario', 'subtotal'];

    protected function casts(): array
    {
        return ['cantidad' => 'integer', 'precio_unitario' => 'integer', 'subtotal' => 'integer'];
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class);
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    /** Movimientos SALIDA que esta línea generó (uno por lote consumido). */
    public function movimientos(): HasMany
    {
        return $this->hasMany(Movimiento::class);
    }
}
