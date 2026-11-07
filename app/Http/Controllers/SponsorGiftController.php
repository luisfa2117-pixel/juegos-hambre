<?php

namespace App\Http\Controllers;

use App\Actions\SendSponsorGift;
use App\Models\Game;
use App\Models\Item;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

class SponsorGiftController extends Controller
{
    public function store(Request $request, Game $game, SendSponsorGift $sendGift): RedirectResponse
    {
        Gate::authorize('sponsor', $game);

        // Volvemos a la misma ronda, y #patrocinar hace que el navegador baje hasta el recuadro
        // (sin el ancla, la página se abriría desde arriba y tendrías que volver a bajar)
        $back = route('games.show', [$game, 'round' => $game->revealed_round]) . '#patrocinar';

        $validator = Validator::make($request->all(), [
            'tribute' => ['required', 'string'],
            'item_id' => ['required', 'integer', 'exists:items,id'],
        ]);

        if ($validator->fails()) {
            return redirect()->to($back)->with('gift_error', 'Elige a quién y qué le envías.');
        }

        $item = Item::findOrFail($request->integer('item_id'));
        $tribute = $request->string('tribute')->toString();

        try {
            $sendGift->handle($game, $tribute, $item);
        } catch (RuntimeException $exception) {
            return redirect()->to($back)->with('gift_error', $exception->getMessage());
        }

        return redirect()->to($back)
            ->with('gift_status', "Regalo enviado: {$item->name} para {$tribute}. Llegará en la siguiente ronda.");
    }
}
