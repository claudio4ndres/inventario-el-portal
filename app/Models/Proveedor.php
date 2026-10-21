<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Proveedor extends Model
{
    use HasFactory;

    protected $table = 'proveedores';

    protected $fillable = ['rut', 'razon_social', 'telefono', 'email', 'activo'];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    public function recepciones(): HasMany
    {
        return $this->hasMany(Recepcion::class);
    }

    /** HU09: un proveedor con recepciones no se elimina, se desactiva. */
    public function tieneHistoria(): bool
    {
        return $this->recepciones()->exists();
    }
}
