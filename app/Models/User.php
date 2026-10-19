<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
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
}
