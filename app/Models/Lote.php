<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Lote extends Model
{
    protected $table = 'lotes';

    protected $fillable = [
        'producto_id', 'recepcion_id', 'codigo_lote', 'fecha_vencimiento',
        'cantidad_inicial', 'cantidad_disponible', 'costo_unitario',
    ];

    protected function casts(): array
    {
        return [
            'fecha_vencimiento' => 'date',
            'cantidad_inicial' => 'integer',
            'cantidad_disponible' => 'integer',
            'costo_unitario' => 'integer',
        ];
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function recepcion(): BelongsTo
    {
        return $this->belongsTo(Recepcion::class);
    }

    /**
     * HU12 / K4: lotes con saldo que vencen dentro de los próximos $dias días,
     * incluidos los ya vencidos (siguen ocupando bodega y deben retirarse).
     * Los no perecibles (fecha_vencimiento NULL) quedan fuera.
     */
    public function scopePorVencer(Builder $query, int $dias): Builder
    {
        return $query->where('cantidad_disponible', '>', 0)
            ->whereNotNull('fecha_vencimiento')
            // whereDate compara solo la fecha, igual en SQLite y en MySQL
            ->whereDate('fecha_vencimiento', '<=', today()->addDays($dias)->toDateString());
    }

    /** Días que faltan para el vencimiento: 0 = vence hoy; negativo = ya vencido. */
    public function diasRestantes(): ?int
    {
        return $this->fecha_vencimiento === null
            ? null
            : (int) round(today()->diffInDays($this->fecha_vencimiento->copy()->startOfDay(), false));
    }

    public function estaVencido(): bool
    {
        return $this->fecha_vencimiento !== null && $this->diasRestantes() < 0;
    }

    /** Valor en riesgo en CLP: saldo × costo unitario. */
    public function valorEnRiesgo(): int
    {
        return $this->cantidad_disponible * $this->costo_unitario;
    }
}
