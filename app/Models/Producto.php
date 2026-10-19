<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Producto extends Model
{
    use HasFactory;

    protected $table = 'productos';

    protected $fillable = [
        'codigo_barras', 'nombre', 'precio_venta', 'punto_reorden', 'clase_abc', 'activo',
    ];

    protected function casts(): array
    {
        return [
            'precio_venta' => 'integer',
            'punto_reorden' => 'integer',
            'activo' => 'boolean',
        ];
    }

    public function lotes(): HasMany
    {
        return $this->hasMany(Lote::class);
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(Movimiento::class);
    }

    /**
     * El stock NO se guarda en una columna: se calcula como la suma de los saldos
     * de sus lotes. Hay una sola fuente de verdad y el stock teórico no puede
     * "desalinearse" de los lotes (C5, K1).
     */
    public function scopeConStock(Builder $query): Builder
    {
        return $query->withSum('lotes as stock', 'cantidad_disponible');
    }

    public function stockActual(): int
    {
        return (int) $this->lotes()->sum('cantidad_disponible');
    }

    /** Productos activos cuyo stock está en o bajo el punto de reorden (K3). */
    public function scopeBajoPuntoReorden(Builder $query): Builder
    {
        return $query->where('activo', true)
            ->where('punto_reorden', '>', 0)
            ->whereRaw(
                '(select coalesce(sum(l.cantidad_disponible), 0) from lotes l where l.producto_id = productos.id) <= productos.punto_reorden'
            );
    }

    public function tieneMovimientos(): bool
    {
        return $this->movimientos()->exists();
    }
}
