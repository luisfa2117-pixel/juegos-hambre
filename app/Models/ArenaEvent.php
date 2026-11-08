<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Una catástrofe de los Vigilantes: afecta a todos los tributos vivos en la misma ronda
 *
 * @property array<int, string>|null $terrains Las arenas donde puede pasar (null = en todas)
 */
class ArenaEvent extends Model
{
    protected $fillable = ['slug', 'name', 'intro', 'terrains'];

    protected $casts = [
        'terrains' => 'array',
    ];

    /**
     * Lo que le puede pasar a cada tributo durante la catástrofe
     *
     * @return HasMany<GameEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(GameEvent::class);
    }
}
