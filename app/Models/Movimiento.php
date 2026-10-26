<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Movimiento extends Model
{
    public const ENTRADA = 'ENTRADA';
    public const SALIDA = 'SALIDA';
    public const AJUSTE = 'AJUSTE';
    public const MERMA = 'MERMA';

    /** HU13: motivos de merma (clave guardada => etiqueta visible). */
    public const MOTIVOS_MERMA = [
        'vencimiento' => 'Vencimiento',
        'dano' => 'Daño',
        'robo' => 'Robo',
        'otro' => 'Otro',
    ];

    protected $table = 'movimientos';

    protected $fillable = [
        'producto_id', 'lote_id', 'user_id', 'tipo', 'cantidad', 'stock_resultante',
        'costo_total', 'motivo_merma', 'detalle_venta_id', 'recepcion_id', 'detalle_conteo_id', 'motivo',
    ];

    protected function casts(): array
    {
        return ['cantidad' => 'integer', 'stock_resultante' => 'integer', 'costo_total' => 'integer'];
    }

    /** Etiqueta legible del motivo de merma (null si no es una merma). */
    public function motivoMermaEtiqueta(): ?string
    {
        return self::MOTIVOS_MERMA[$this->motivo_merma] ?? null;
    }

    /**
     * Signo con que el movimiento afecta el stock: las entradas suman y las salidas y mermas restan.
     * Un AJUSTE (HU14) suma si el conteo encontró más unidades que el sistema y resta si encontró menos.
     */
    public function signo(): string
    {
        return match ($this->tipo) {
            self::ENTRADA => '+',
            self::AJUSTE => ($this->detalleConteo?->diferencia ?? 0) > 0 ? '+' : '−',
            default => '−',
        };
    }

    /** HU14: línea del conteo que originó el AJUSTE. */
    public function detalleConteo(): BelongsTo
    {
        return $this->belongsTo(DetalleConteo::class);
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function lote(): BelongsTo
    {
        return $this->belongsTo(Lote::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
