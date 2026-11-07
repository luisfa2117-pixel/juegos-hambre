<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    // is_admin NO está aquí a propósito: si estuviera, alguien podría añadir
    // is_admin=1 al formulario de registro y hacerse administrador (asignación masiva)
    protected $fillable = ['name', 'email', 'password'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',   // al guardar, Laravel cifra la contraseña solo
            'is_admin' => 'boolean',
        ];
    }

    public function characters(): HasMany
    {
        return $this->hasMany(Character::class);
    }

    public function games(): HasMany
    {
        return $this->hasMany(Game::class);
    }
}
