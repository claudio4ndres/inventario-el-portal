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
        'costo_total', 'motivo_merma', 'detalle_venta_id', 'recepcion_id', 'motivo',
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
