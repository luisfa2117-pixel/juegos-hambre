<?php

namespace App\Models;

use App\Enums\Gender;
use App\Simulation\Tribute;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

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

    /** Al borrar un personaje, se borra también su foto del disco */
    protected static function booted(): void
    {
        static::deleted(fn (Character $character) => $character->deleteImage());
    }

    /**
     * La dirección web de la foto, o null si no tiene. Se usa como $character->image_url
     * (Laravel convierte el nombre del método, imageUrl, a image_url).
     */
    protected function imageUrl(): Attribute
    {
        return Attribute::get(fn () => $this->image_path
            ? Storage::disk('public')->url($this->image_path)
            : null);
    }

    public function deleteImage(): void
    {
        if ($this->image_path) {
            Storage::disk('public')->delete($this->image_path);
        }
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    /** Sus participaciones en partidas guardadas */
    public function gameTributes(): HasMany
    {
        return $this->hasMany(GameTribute::class);
    }

    /** Crea el tributo que lo representa en una partida, con los stats iniciales. */
    public function toTribute(): Tribute
    {
        return new Tribute($this->name, $this->gender, $this->district);
    }
}
