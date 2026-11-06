<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameRound extends Model
{
    protected $fillable = ['number', 'events', 'tributes'];

    // Postgres guarda jsonb; Laravel lo convierte a array de PHP y viceversa
    protected $casts = [
        'events' => 'array',
        'tributes' => 'array',
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }
}
