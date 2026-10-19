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

    protected $table = 'movimientos';

    protected $fillable = [
        'producto_id', 'lote_id', 'user_id', 'tipo', 'cantidad', 'stock_resultante',
        'detalle_venta_id', 'recepcion_id', 'motivo',
    ];

    protected function casts(): array
    {
        return ['cantidad' => 'integer', 'stock_resultante' => 'integer'];
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
