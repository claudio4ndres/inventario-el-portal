<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** HU11: línea de una orden de compra (intersección entre ORDENES_COMPRA y PRODUCTOS). */
class DetalleOrdenCompra extends Model
{
    protected $table = 'detalle_ordenes_compra';

    protected $fillable = ['orden_compra_id', 'producto_id', 'cantidad_sugerida', 'cantidad'];

    protected function casts(): array
    {
        return ['producto_id' => 'integer', 'cantidad_sugerida' => 'integer', 'cantidad' => 'integer'];
    }

    public function ordenCompra(): BelongsTo
    {
        return $this->belongsTo(OrdenCompra::class);
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }
}
