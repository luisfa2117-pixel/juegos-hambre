<?php

namespace App\Models;

use App\Enums\Terrain;
use App\Simulation\GameRules;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property array<int, string> $rules
 * @property bool $quell
 * @property Terrain $terrain
 */
class Game extends Model
{
    protected $fillable = ['seed', 'total_rounds', 'revealed_round', 'rules', 'quell', 'terrain'];

    protected $casts = [
        'terrain' => Terrain::class,
        'rules' => 'array',
        'quell' => 'boolean',
    ];

    /** Las reglas especiales de la partida, listas para el motor */
    public function gameRules(): GameRules
    {
        return GameRules::fromValues($this->rules);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Los tributos de la partida, del ganador al primer muerto
     *
     * @return HasMany<GameTribute, $this>
     */
    public function tributes(): HasMany
    {
        return $this->hasMany(GameTribute::class)->orderBy('placement');
    }

    /**
     * Los regalos de patrocinadores, en el orden en que se enviaron
     *
     * @return HasMany<GameGift, $this>
     */
    public function gifts(): HasMany
    {
        return $this->hasMany(GameGift::class)->orderBy('id');
    }

    /** @return HasMany<GameKill, $this> */
    public function kills(): HasMany
    {
        return $this->hasMany(GameKill::class);
    }

    /** El tributo al que apostaste, si apostaste */
    public function bet(): ?GameTribute
    {
        return $this->tributes->firstWhere('is_bet', true);
    }

    /** ¿Ya se sabe si acertaste? Solo cuando has visto la partida entera (los regalos aún pueden cambiarla) */
    public function betSettled(): bool
    {
        return $this->bet() !== null && $this->revealed_round >= $this->total_rounds;
    }

    /** @return HasMany<GameRound, $this> */
    public function rounds(): HasMany
    {
        return $this->hasMany(GameRound::class)->orderBy('number');
    }

    /** El único superviviente, o null si murieron todos. */
    public function winner(): ?GameTribute
    {
        $survivors = $this->tributes->whereNull('died_in_round');

        return $survivors->count() === 1 ? $survivors->first() : null;
    }
}
