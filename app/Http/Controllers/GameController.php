<?php

namespace App\Http\Controllers;

use App\Actions\PlayGame;
use App\Actions\SendSponsorGift;
use App\Enums\GameRule;
use App\Http\Requests\GameRequest;
use App\Models\District;
use App\Models\Game;
use App\Models\GameTribute;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class GameController extends Controller
{
    public function index(Request $request): View
    {
        return view('games.index', [
            'games' => $request->user()->games()->with(['tributes.district', 'tributes.character'])->latest()->paginate(15),
        ]);
    }

    public function create(Request $request): View
    {
        $mine = fn ($query) => $query->whereBelongsTo($request->user());

        $districts = District::orderBy('name')
            ->whereHas('characters', $mine)   // solo distritos donde tienes algún personaje
            ->with(['characters' => fn ($query) => $mine($query)->orderBy('name')])
            ->get();

        $allIds = $districts->flatMap->characters->pluck('id')->all();

        return view('games.create', [
            'districts' => $districts,
            'allIds' => $allIds,
            'totalCharacters' => count($allIds),
            'gameRules' => GameRule::cases(),
        ]);
    }

    // PlayGame llega por inyección de dependencias, igual que en el comando game:play
    public function store(GameRequest $request, PlayGame $playGame): RedirectResponse
    {
        $limit = config('juegos.limits.games');

        if ($request->user()->games()->count() >= $limit) {
            return back()->with('error', "Tienes {$limit} partidas guardadas, que es el máximo. Borra alguna para jugar más.");
        }

        $characters = $request->user()->characters()->whereIn('id', $request->validated('characters'))->get();
        $game = $playGame->handle(
            $request->user(),
            $characters,
            $request->validated('seed'),
            $request->gameRules(),
            $request->boolean('quell'),
        );

        return redirect()->route('games.show', [$game, 'round' => 1]);
    }

    /** Muestra las rondas de la 1 a la que diga ?round=N */
    public function show(Request $request, Game $game): View
    {
        Gate::authorize('view', $game);

        $game->load(['tributes.district', 'tributes.character', 'rounds']);

        // Si alguien pone ?round=999 o ?round=-3 en la URL, lo dejamos dentro de los límites
        $round = min(max((int) $request->query('round', 1), 1), $game->total_rounds);

        // Lo que ya has visto, ha pasado: los patrocinadores solo pueden cambiar lo que viene después
        if ($round > $game->revealed_round) {
            $game->update(['revealed_round' => $round]);
        }

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

            // Patrocinadores: solo en la última ronda que has visto
            'canSponsor' => $round === $game->revealed_round && SendSponsorGift::unavailableReason($game) === null,
            'sponsorBlocked' => $round === $game->revealed_round ? SendSponsorGift::unavailableReason($game) : null,
            'giftsLeft' => SendSponsorGift::MAX_GIFTS - $game->gifts()->count(),
            'catalog' => SendSponsorGift::catalog(),
        ]);
    }

    public function destroy(Game $game): RedirectResponse
    {
        Gate::authorize('delete', $game);

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
