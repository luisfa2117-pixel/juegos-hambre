<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * Los tipos de estas columnas, tal como los deja el cast de casts().
 * Larastan los saca de las migraciones y no siempre ve el cast: se los decimos aquí.
 *
 * @property bool $is_admin
 * @property bool $is_guest
 * @property Carbon|null $last_seen_at
 */
class User extends Authenticatable
{
    use HasFactory, Notifiable;

    // is_admin e is_guest NO están aquí a propósito: si estuvieran, alguien podría añadir
    // is_admin=1 al formulario de registro y hacerse administrador (asignación masiva)
    protected $fillable = ['name', 'email', 'password'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',   // al guardar, Laravel cifra la contraseña solo
            'is_admin' => 'boolean',
            'is_guest' => 'boolean',
            'last_seen_at' => 'datetime',
        ];
    }

    /** @return HasMany<Character, $this> */
    public function characters(): HasMany
    {
        return $this->hasMany(Character::class);
    }

    /** @return HasMany<Game, $this> */
    public function games(): HasMany
    {
        return $this->hasMany(Game::class);
    }
}
