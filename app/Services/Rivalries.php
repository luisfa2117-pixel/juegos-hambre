<?php

namespace App\Services;

use App\Models\Character;
use App\Models\GameKill;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Quién mata a quién a lo largo de todas las partidas: némesis, víctimas favoritas y rivalidades.
 * Solo cuentan los personajes que siguen existiendo (de los borrados no sabemos quiénes eran).
 */
class Rivalries
{
    /** Quien más veces ha matado a este personaje */
    public function nemesisOf(Character $character): ?Rivalry
    {
        return $this->toRivalry($this->pairs()->where('victims.character_id', $character->id)->first());
    }

    /** A quien más veces ha matado este personaje */
    public function favouriteVictimOf(Character $character): ?Rivalry
    {
        return $this->toRivalry($this->pairs()->where('killers.character_id', $character->id)->first());
    }

    /**
     * Las parejas asesino-víctima que más se repiten en las partidas de un usuario
     *
     * @return Collection<int, Rivalry>
     */
    public function topFor(User $user, int $limit = 5): Collection
    {
        return $this->pairs()
            ->whereHas('game', fn (Builder $query) => $query->whereBelongsTo($user))
            ->havingRaw('count(*) >= 2')   // una vez es casualidad; dos, ya es personal
            ->limit($limit)
            ->get()
            ->map(fn (GameKill $row) => $this->toRivalry($row))
            ->filter()
            ->values();
    }

    /**
     * Cuenta los asesinatos por pareja (personaje asesino, personaje víctima), de más a menos.
     *
     * @return Builder<GameKill>
     */
    private function pairs(): Builder
    {
        return GameKill::query()
            ->join('game_tributes as killers', 'killers.id', '=', 'game_kills.killer_id')
            ->join('game_tributes as victims', 'victims.id', '=', 'game_kills.victim_id')
            ->whereNotNull('killers.character_id')
            ->whereNotNull('victims.character_id')
            ->selectRaw('killers.character_id as killer_character_id, victims.character_id as victim_character_id, count(*) as total')
            ->groupBy('killers.character_id', 'victims.character_id')
            ->orderByDesc('total')
            // Si empatan, la pareja que empezó antes: así sale siempre la misma
            ->orderByRaw('min(game_kills.id)');
    }

    /** De una fila de pairs() a una Rivalry con los dos personajes cargados */
    private function toRivalry(?GameKill $row): ?Rivalry
    {
        if ($row === null) {
            return null;
        }

        $killerId = (int) $row->getAttribute('killer_character_id');
        $victimId = (int) $row->getAttribute('victim_character_id');
        $characters = Character::with('district')->whereIn('id', [$killerId, $victimId])->get()->keyBy('id');

        $killer = $characters->get($killerId);
        $victim = $characters->get($victimId);

        return $killer && $victim ? new Rivalry($killer, $victim, (int) $row->getAttribute('total')) : null;
    }
}
