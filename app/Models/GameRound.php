<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameRound extends Model
{
    protected $fillable = ['number', 'events', 'tributes', 'state'];

    // Postgres guarda jsonb; Laravel lo convierte a array de PHP y viceversa
    protected $casts = [
        'events' => 'array',     // lo que pasó (para el relato)
        'tributes' => 'array',   // cómo quedó cada tributo (para mostrarlo)
        'state' => 'array',      // el estado exacto, para poder continuar desde aquí (null en partidas antiguas)
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }
}
