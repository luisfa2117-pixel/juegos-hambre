<?php

namespace App\Http\Controllers;

use App\Models\Character;
use App\Models\District;
use App\Models\Game;
use App\Models\GameTribute;
use Illuminate\View\View;

class StatsController extends Controller
{
    /** El salón de la fama: rankings con todas las partidas jugadas */
    public function index(): View
    {
        // Una sola consulta trae a todos los personajes con sus estadísticas (ver Character::scopeWithStats)
        $characters = Character::withStats()->with('district')->get()
            ->filter(fn (Character $character) => $character->games_count > 0);

        $top = fn (string $stat) => $characters
            ->filter(fn (Character $character) => $character->{$stat} > 0)
            ->sortByDesc($stat)
            ->take(5);

        return view('stats.index', [
            'totalGames' => Game::count(),
            'mostWins' => $top('wins_count'),
            'mostKills' => $top('kills_total'),
            'bloodbath' => $top('bloodbath_deaths_count'),

            // Asesinatos por partida, solo entre quienes han jugado al menos 3 (con 1 partida no es justo)
            'deadliest' => $characters
                ->filter(fn (Character $character) => $character->games_count >= 3 && $character->kills_total > 0)
                ->sortByDesc(fn (Character $character) => $character->kills_total / $character->games_count)
                ->take(5),

            // Las 5 actuaciones más sangrientas en una sola partida
            'bloodiestGames' => GameTribute::with(['district', 'character'])
                ->where('kills', '>', 0)
                ->orderByDesc('kills')
                ->orderBy('id')
                ->limit(5)
                ->get(),

            'districts' => $this->districtStats(),
        ]);
    }

    /** Por distrito: cuántas veces ha participado alguien de allí y cuántas ha ganado */
    private function districtStats()
    {
        // selectRaw + groupBy: Postgres cuenta y nos devuelve [district_id => total]
        $played = GameTribute::selectRaw('district_id, count(*) as total')->groupBy('district_id')->pluck('total', 'district_id');
        $won = GameTribute::where('placement', 1)->whereNull('died_in_round')
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
}
