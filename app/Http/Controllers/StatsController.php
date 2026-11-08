<?php

namespace App\Http\Controllers;

use App\Models\Character;
use App\Models\District;
use App\Models\Game;
use App\Models\GameTribute;
use App\Models\User;
use App\Services\Rivalries;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StatsController extends Controller
{
    /** El salón de la fama: rankings con todas las partidas jugadas */
    public function index(Request $request, Rivalries $rivalries): View
    {
        $user = $request->user();

        // Una sola consulta trae a tus personajes con sus estadísticas (ver Character::scopeWithStats)
        $characters = Character::withStats()->whereBelongsTo($user)->with('district')->get()
            ->filter(fn (Character $character) => $character->games_count > 0);

        $top = fn (string $stat) => $characters
            ->filter(fn (Character $character) => $character->{$stat} > 0)
            ->sortByDesc($stat)
            ->take(5);

        return view('stats.index', [
            'totalGames' => Game::whereBelongsTo($user)->count(),
            'mostWins' => $top('wins_count'),
            'mostKills' => $top('kills_total'),
            'bloodbath' => $top('bloodbath_deaths_count'),

            // Asesinatos por partida, solo entre quienes han jugado al menos 3 (con 1 partida no es justo)
            'deadliest' => $characters
                ->filter(fn (Character $character) => $character->games_count >= 3 && $character->kills_total > 0)
                ->sortByDesc(fn (Character $character) => $character->kills_total / $character->games_count)
                ->take(5),

            // Las 5 actuaciones más sangrientas en una sola partida
            'bloodiestGames' => $this->tributesOf($user)
                ->with(['district', 'character'])
                ->where('kills', '>', 0)
                ->orderByDesc('kills')
                ->orderBy('id')
                ->limit(5)
                ->get(),

            'districts' => $this->districtStats($user),
            'bets' => $this->betStats($user),
            'rivalries' => $rivalries->topFor($user),
            'personalities' => $this->personalityStats($characters),
        ]);
    }

    /**
     * Tus apuestas: cuántas has acertado. Solo cuentan las partidas que has visto enteras,
     * porque hasta el final un regalo de patrocinador aún puede cambiar quién gana.
     */
    private function betStats(User $user): object
    {
        $bets = $this->tributesOf($user)->where('is_bet', true);
        $settled = (clone $bets)->whereHas('game', fn (Builder $query) => $query->whereColumn('revealed_round', '>=', 'total_rounds'));

        return (object) [
            'total' => (clone $bets)->count(),
            'settled' => (clone $settled)->count(),
            'won' => (clone $settled)->where('placement', 1)->whereNull('died_in_round')->count(),
        ];
    }

    /** Por distrito: cuántas veces ha participado alguien de allí y cuántas ha ganado */
    private function districtStats(User $user)
    {
        // selectRaw + groupBy: Postgres cuenta y nos devuelve [district_id => total]
        $played = $this->tributesOf($user)->selectRaw('district_id, count(*) as total')->groupBy('district_id')->pluck('total', 'district_id');
        $won = $this->tributesOf($user)->where('placement', 1)->whereNull('died_in_round')
            ->selectRaw('district_id, count(*) as total')->groupBy('district_id')->pluck('total', 'district_id');

        return District::orderBy('name')->get()
            ->map(fn (District $district) => (object) [
                'district' => $district,
                'played' => (int) ($played[$district->id] ?? 0),
                'won' => (int) ($won[$district->id] ?? 0),
            ])
            ->filter(fn (object $row) => $row->played > 0)
            ->sortByDesc(fn (object $row) => $row->won / $row->played);
    }

    /**
     * Por personalidad: cuántas veces han jugado y ganado los personajes que la tienen.
     * Se calcula a partir de las estadísticas que ya trae cada personaje (withStats).
     */
    private function personalityStats($characters)
    {
        return $characters
            // ?? ya admite que personality sea null, así que basta con -> (con métodos, en cambio, hace falta ?->)
            ->groupBy(fn (Character $character) => $character->personality->value ?? 'balanced')
            ->map(fn ($group) => (object) [
                'label' => $group->first()->personality?->label() ?? 'Equilibrado',
                'played' => $group->sum('games_count'),
                'won' => $group->sum('wins_count'),
            ])
            ->filter(fn (object $row) => $row->played > 0)
            ->sortByDesc(fn (object $row) => $row->won / $row->played)
            ->values();
    }

    /** Los tributos de las partidas de este usuario */
    private function tributesOf(User $user): Builder
    {
        return GameTribute::whereHas('game', fn (Builder $query) => $query->whereBelongsTo($user));
    }
}
