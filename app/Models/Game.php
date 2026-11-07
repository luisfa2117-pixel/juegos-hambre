<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Game extends Model
{
    protected $fillable = ['seed', 'total_rounds'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Los tributos de la partida, del ganador al primer muerto */
    public function tributes(): HasMany
    {
        return $this->hasMany(GameTribute::class)->orderBy('placement');
    }

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
