<?php

namespace App\Http\Controllers;

use App\Actions\PlayGame;
use App\Http\Requests\GameRequest;
use App\Models\Character;
use App\Models\District;
use App\Models\Game;
use App\Models\GameTribute;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GameController extends Controller
{
    public function index(): View
    {
        return view('games.index', [
            'games' => Game::with(['tributes.district', 'tributes.character'])->latest()->paginate(15),
        ]);
    }

    public function create(): View
    {
        $districts = District::orderBy('name')
            ->whereHas('characters')   // solo distritos con algún personaje
            ->with(['characters' => fn ($query) => $query->orderBy('name')])
            ->get();

        $allIds = $districts->flatMap->characters->pluck('id')->all();

        return view('games.create', [
            'districts' => $districts,
            'allIds' => $allIds,
            'totalCharacters' => count($allIds),
        ]);
    }

    // PlayGame llega por inyección de dependencias, igual que en el comando game:play
    public function store(GameRequest $request, PlayGame $playGame): RedirectResponse
    {
        $characters = Character::whereIn('id', $request->validated('characters'))->get();
        $game = $playGame->handle($characters, $request->validated('seed'));

        return redirect()->route('games.show', [$game, 'round' => 1]);
    }

    /** Muestra las rondas de la 1 a la que diga ?round=N */
    public function show(Request $request, Game $game): View
    {
        $game->load(['tributes.district', 'tributes.character', 'rounds']);

        // Si alguien pone ?round=999 o ?round=-3 en la URL, lo dejamos dentro de los límites
        $round = min(max((int) $request->query('round', 1), 1), $game->total_rounds);

        return view('games.show', [
            'game' => $game,
            'round' => $round,
            'finished' => $round === $game->total_rounds,
            'visibleRounds' => $game->rounds->take($round),
            'snapshot' => $this->sortedSnapshot($game->rounds[$round - 1]->tributes),
            'tributesByName' => $game->tributes->keyBy('name'),
            'tributeNames' => $game->tributes->pluck('name'),
            'winner' => $game->winner(),
            // Solo se puede repetir si siguen existiendo todos los personajes
            'canReplay' => $game->tributes->every(fn (GameTribute $t) => $t->character_id !== null),
        ]);
    }

    public function destroy(Game $game): RedirectResponse
    {
        $game->delete();

        return redirect()->route('games.index')->with('status', "Partida #{$game->id} eliminada.");
    }

    /** Primero los vivos (con más vida arriba) y después los muertos */
    private function sortedSnapshot(array $tributes): array
    {
        usort($tributes, fn (array $a, array $b) => [$b['alive'], $b['hp']] <=> [$a['alive'], $a['hp']]);

        return $tributes;
    }
}
