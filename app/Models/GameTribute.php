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
        'kills', 'killed_by', 'is_bet',
    ];

    protected $casts = [
        'gender' => Gender::class,
        'is_bet' => 'boolean',
    ];

    /** ¿Ganó la partida? (puesto 1 y vivo: si mueren todos a la vez, el puesto 1 no es una victoria) */
    public function won(): bool
    {
        return $this->placement === 1 && $this->died_in_round === null;
    }

    /** @return BelongsTo<Game, $this> */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /** @return BelongsTo<Character, $this> */
    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class);
    }

    /** @return BelongsTo<District, $this> */
    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }
}
