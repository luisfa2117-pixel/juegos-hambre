<?php

namespace App\Models;

use App\Enums\Gender;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un tributo en una partida concreta. Guarda una copia del nombre, género y distrito
 * del personaje: si mañana lo editas o lo borras, la partida guardada no cambia.
 */
class GameTribute extends Model
{
    protected $fillable = [
        'character_id', 'name', 'gender', 'district_id', 'died_in_round', 'placement',
        'kills', 'killed_by',
    ];

    protected $casts = [
        'gender' => Gender::class,
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class);
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }
}
