<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Game extends Model
{
    protected $fillable = ['seed', 'total_rounds', 'revealed_round'];

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
