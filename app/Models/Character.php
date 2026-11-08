<?php

namespace App\Models;

use App\Enums\Gender;
use App\Enums\Personality;
use App\Simulation\Tribute;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use LogicException;

/**
 * Un personaje del catálogo: lo creas una vez y puede participar en muchas partidas.
 * Durante una partida, se convierte en un Tribute (con su vida, mochila...).
 *
 * Estas propiedades no son columnas: solo existen si la consulta usa withStats().
 * Se declaran aquí para que PHPStan (y tu editor) sepan que existen y de qué tipo son.
 *
 * @property-read int $games_count
 * @property-read int $wins_count
 * @property-read int $deaths_count
 * @property-read int $bloodbath_deaths_count
 * @property-read int|null $kills_total  withSum devuelve null si no tiene ninguna partida
 */
class Character extends Model
{
    // user_id no se rellena desde formularios: se asigna con $user->characters()->make(...)
    protected $fillable = ['name', 'gender', 'district_id', 'personality'];

    protected $casts = [
        'gender' => Gender::class,
        'personality' => Personality::class,   // null = equilibrado
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

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<District, $this> */
    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    /**
     * Sus participaciones en partidas guardadas
     *
     * @return HasMany<GameTribute, $this>
     */
    public function gameTributes(): HasMany
    {
        return $this->hasMany(GameTribute::class);
    }

    /**
     * Añade a la consulta sus estadísticas en todas las partidas, calculadas por Postgres:
     * games_count, wins_count, deaths_count, bloodbath_deaths_count y kills_total.
     *
     * Uso: Character::withStats()->get()
     */
    public function scopeWithStats(Builder $query): void
    {
        $query
            ->withCount([
                'gameTributes as games_count',
                'gameTributes as wins_count' => fn (Builder $q) => $q->where('placement', 1)->whereNull('died_in_round'),
                'gameTributes as deaths_count' => fn (Builder $q) => $q->whereNotNull('died_in_round'),
                'gameTributes as bloodbath_deaths_count' => fn (Builder $q) => $q->where('died_in_round', 1),
            ])
            ->withSum('gameTributes as kills_total', 'kills');
    }

    /** Crea el tributo que lo representa en una partida, con los stats iniciales. */
    public function toTribute(): Tribute
    {
        $district = $this->district ?? throw new LogicException("El personaje {$this->name} no tiene distrito");

        return new Tribute($this->name, $this->gender, $district, $this->personality);
    }
}
