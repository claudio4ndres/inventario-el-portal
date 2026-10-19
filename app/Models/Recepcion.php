<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Recepcion extends Model
{
    protected $table = 'recepciones';

    protected $fillable = ['proveedor_id', 'user_id', 'numero_documento', 'fecha_recepcion'];

    protected function casts(): array
    {
        return ['fecha_recepcion' => 'datetime'];
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function lotes(): HasMany
    {
        return $this->hasMany(Lote::class);
    }
}
