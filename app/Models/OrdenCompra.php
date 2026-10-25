<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/** HU11: orden de compra a un proveedor (borrador → enviada → recibida). */
class OrdenCompra extends Model
{
    public const BORRADOR = 'borrador';
    public const ENVIADA = 'enviada';
    public const RECIBIDA = 'recibida';

    /** Estado guardado => etiqueta visible. */
    public const ESTADOS = [
        self::BORRADOR => 'Borrador',
        self::ENVIADA => 'Enviada',
        self::RECIBIDA => 'Recibida',
    ];

    protected $table = 'ordenes_compra';

    protected $fillable = ['proveedor_id', 'user_id', 'estado', 'fecha_envio', 'fecha_recepcion'];

    protected function casts(): array
    {
        return [
            'proveedor_id' => 'integer',
            'user_id' => 'integer',
            'fecha_envio' => 'datetime',
            'fecha_recepcion' => 'datetime',
        ];
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(DetalleOrdenCompra::class);
    }

    public function recepciones(): HasMany
    {
        return $this->hasMany(Recepcion::class);
    }

    /** OC que todavía no se reciben: sus productos no vuelven a sugerirse. */
    public function scopeAbiertas(Builder $query): Builder
    {
        return $query->whereIn('estado', [self::BORRADOR, self::ENVIADA]);
    }

    public function esBorrador(): bool
    {
        return $this->estado === self::BORRADOR;
    }

    public function etiquetaEstado(): string
    {
        return self::ESTADOS[$this->estado] ?? $this->estado;
    }

    /**
     * Compara lo pedido con lo recibido en las recepciones asociadas, producto por producto.
     * Incluye los productos recibidos que no estaban en la OC (pedida = 0).
     * diferencia = recibida − pedida: negativa si faltó, positiva si llegó de más.
     *
     * @return Collection<int, object{producto: Producto, pedida: int, recibida: int, diferencia: int}>
     */
    public function diferencias(): Collection
    {
        $pedidas = $this->detalles()->pluck('cantidad', 'producto_id')
            ->mapWithKeys(fn ($cantidad, $productoId) => [(int) $productoId => (int) $cantidad]);

        $recibidas = Lote::query()
            ->whereIn('recepcion_id', Recepcion::where('orden_compra_id', $this->id)->select('id'))
            ->groupBy('producto_id')
            ->selectRaw('producto_id, sum(cantidad_inicial) as total')
            ->pluck('total', 'producto_id')
            ->mapWithKeys(fn ($total, $productoId) => [(int) $productoId => (int) $total]);

        $ids = $pedidas->keys()->merge($recibidas->keys())->unique()->values();
        $productos = Producto::whereIn('id', $ids)->get()->keyBy('id');

        return $ids->map(function (int $id) use ($pedidas, $recibidas, $productos) {
            $pedida = $pedidas->get($id, 0);
            $recibida = $recibidas->get($id, 0);

            return (object) [
                'producto' => $productos[$id],
                'pedida' => $pedida,
                'recibida' => $recibida,
                'diferencia' => $recibida - $pedida,
            ];
        })->sortBy(fn ($fila) => $fila->producto->nombre)->values();
    }
}
