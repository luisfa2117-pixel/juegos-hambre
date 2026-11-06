<?php

namespace App\Models;

use App\Enums\Gender;
use App\Simulation\Tribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un personaje del catálogo: lo creas una vez y puede participar en muchas partidas.
 * Durante una partida, se convierte en un Tribute (con su vida, mochila...).
 */
class Character extends Model
{
    protected $fillable = ['name', 'gender', 'district_id'];

    protected $casts = [
        'gender' => Gender::class,
    ];

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    /** Crea el tributo que lo representa en una partida, con los stats iniciales. */
    public function toTribute(): Tribute
    {
        return new Tribute($this->name, $this->gender, $this->district);
    }
}
