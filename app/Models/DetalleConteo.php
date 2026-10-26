<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** HU14: línea de un conteo (intersección entre CONTEOS y PRODUCTOS). */
class DetalleConteo extends Model
{
    protected $table = 'detalle_conteos';

    protected $fillable = ['conteo_id', 'producto_id', 'stock_sistema', 'cantidad_contada', 'diferencia', 'motivo'];

    protected function casts(): array
    {
        return [
            'producto_id' => 'integer',
            'stock_sistema' => 'integer',
            'cantidad_contada' => 'integer',
            'diferencia' => 'integer',
        ];
    }

    public function conteo(): BelongsTo
    {
        return $this->belongsTo(Conteo::class);
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    /** Movimientos AJUSTE generados por la diferencia (uno por lote afectado). */
    public function movimientos(): HasMany
    {
        return $this->hasMany(Movimiento::class);
    }
}
