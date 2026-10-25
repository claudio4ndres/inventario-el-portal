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
        'codigo_barras', 'nombre', 'precio_venta', 'punto_reorden', 'dias_reposicion', 'stock_seguridad',
        'clase_abc', 'activo',
    ];

    protected function casts(): array
    {
        return [
            'precio_venta' => 'integer',
            'punto_reorden' => 'integer',
            'dias_reposicion' => 'integer',   // HU10
            'stock_seguridad' => 'integer',   // HU10
            'activo' => 'boolean',
        ];
    }

    public function lotes(): HasMany
    {
        return $this->hasMany(Lote::class);
    }

    /** HU11: líneas de órdenes de compra en que aparece el producto. */
    public function detallesOrdenCompra(): HasMany
    {
        return $this->hasMany(DetalleOrdenCompra::class);
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

    /**
     * HU11: agrega la columna "ultimo_proveedor_id": el proveedor de la recepción más reciente
     * del producto. Es el proveedor al que se le sugiere comprar (no hay una tabla producto-proveedor).
     */
    public function scopeConUltimoProveedor(Builder $query): Builder
    {
        return $query->addSelect(['ultimo_proveedor_id' => Lote::query()
            ->select('recepciones.proveedor_id')
            ->join('recepciones', 'recepciones.id', '=', 'lotes.recepcion_id')
            ->whereColumn('lotes.producto_id', 'productos.id')
            ->orderByDesc('lotes.id')
            ->limit(1),
        ]);
    }

    /** HU11: productos que ya están en una orden de compra en borrador o enviada. */
    public function scopeSinOrdenAbierta(Builder $query): Builder
    {
        return $query->whereDoesntHave('detallesOrdenCompra', fn (Builder $q) => $q->whereHas(
            'ordenCompra', fn (Builder $oc) => $oc->abiertas()
        ));
    }

    public function tieneMovimientos(): bool
    {
        return $this->movimientos()->exists();
    }
}
