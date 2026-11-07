<?php

namespace App\Http\Controllers;

use App\Actions\SendSponsorGift;
use App\Models\Game;
use App\Models\Item;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

class SponsorGiftController extends Controller
{
    public function store(Request $request, Game $game, SendSponsorGift $sendGift): RedirectResponse
    {
        Gate::authorize('sponsor', $game);

        $data = $request->validate([
            'tribute' => ['required', 'string'],
            'item_id' => ['required', 'integer', 'exists:items,id'],
        ], [
            'tribute.required' => 'Elige a quién se lo envías.',
            'item_id.required' => 'Elige qué le envías.',
            'item_id.exists' => 'Ese objeto no existe.',
        ]);

        $item = Item::findOrFail($data['item_id']);

        try {
            $sendGift->handle($game, $data['tribute'], $item);
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('games.show', [$game, 'round' => $game->revealed_round])
            ->with('status', "Regalo enviado: {$item->name} para {$data['tribute']}. Llegará en la siguiente ronda.");
    }
}
