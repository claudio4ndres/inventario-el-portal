<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'rol', 'activo'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ADMINISTRADOR = 'administrador';
    public const VENDEDOR = 'vendedor';
    public const BODEGUERO = 'bodeguero';

    public const ROLES = [self::ADMINISTRADOR, self::VENDEDOR, self::BODEGUERO];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',   // K8 / RNF2: contraseñas con hash (bcrypt)
            'activo' => 'boolean',
        ];
    }

    public function tieneRol(string ...$roles): bool
    {
        return in_array($this->rol, $roles, true);
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(Movimiento::class, 'user_id');
    }

    public function ventas(): HasMany
    {
        return $this->hasMany(Venta::class, 'user_id');
    }

    public function recepciones(): HasMany
    {
        return $this->hasMany(Recepcion::class, 'user_id');
    }

    /** HU08: un usuario con historia no se elimina, se desactiva (FK con RESTRICT). */
    public function tieneHistoria(): bool
    {
        return $this->movimientos()->exists()
            || $this->ventas()->exists()
            || $this->recepciones()->exists();
    }
}
