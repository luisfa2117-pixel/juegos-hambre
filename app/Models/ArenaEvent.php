<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Una catástrofe de los Vigilantes: afecta a todos los tributos vivos en la misma ronda */
class ArenaEvent extends Model
{
    protected $fillable = ['slug', 'name', 'intro'];

    /** Lo que le puede pasar a cada tributo durante la catástrofe */
    public function events(): HasMany
    {
        return $this->hasMany(GameEvent::class);
    }
}
