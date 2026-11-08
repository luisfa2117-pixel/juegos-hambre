<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Un asesinato en una partida. Lo calcula el GameRecorder a partir de las rondas guardadas */
class GameKill extends Model
{
    public $timestamps = false;

    protected $fillable = ['game_id', 'killer_id', 'victim_id', 'round'];

    /** @return BelongsTo<Game, $this> */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /** @return BelongsTo<GameTribute, $this> */
    public function killer(): BelongsTo
    {
        return $this->belongsTo(GameTribute::class, 'killer_id');
    }

    /** @return BelongsTo<GameTribute, $this> */
    public function victim(): BelongsTo
    {
        return $this->belongsTo(GameTribute::class, 'victim_id');
    }
}
