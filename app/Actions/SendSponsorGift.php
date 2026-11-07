<?php

namespace App\Actions;

use App\Models\Game;
use App\Models\GameGift;
use App\Models\Item;
use App\Simulation\EventResult;
use App\Simulation\Simulation;
use App\Simulation\Tribute;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Un patrocinador envía un objeto a un tributo. Llega al empezar la ronda siguiente
 * a la última que has visto, y todo lo que viene después se vuelve a jugar.
 */
class SendSponsorGift
{
    /** Regalos por partida: si fueran infinitos, el patrocinador elegiría al ganador */
    public const MAX_GIFTS = 3;

    /** Lo que se puede regalar. Nada de rifles de riel: un patrocinador ayuda, no decide la partida */
    public const CATALOG = [
        'vendas', 'botiquin', 'nanobots',
        'barritas', 'bebida_energetica', 'estimulante',
        'casco', 'chaleco',
        'cuchillo', 'machete', 'ballesta',
    ];

    public function __construct(private readonly ResumeGame $resumeGame)
    {
    }

    /** Los objetos del catálogo, en el orden en que se muestran */
    public static function catalog()
    {
        return Item::whereIn('slug', self::CATALOG)->get()->sortBy(fn (Item $item) => array_search($item->slug, self::CATALOG))->values();
    }

    /** Por qué no se puede regalar ahora mismo en esta partida, o null si se puede */
    public static function unavailableReason(Game $game): ?string
    {
        return match (true) {
            $game->revealed_round >= $game->total_rounds => 'Ya has visto el final de la partida: lo que ha pasado no se puede cambiar.',
            $game->gifts()->count() >= self::MAX_GIFTS => 'Ya has enviado los ' . self::MAX_GIFTS . ' regalos de esta partida.',
            $game->rounds()->where('number', $game->revealed_round)->value('state') === null => 'Esta partida se jugó antes de que existieran los patrocinadores.',
            default => null,
        };
    }

    /** @throws RuntimeException con un mensaje para mostrar si el regalo no es posible */
    public function handle(Game $game, string $tributeName, Item $item): Game
    {
        if (($reason = self::unavailableReason($game)) !== null) {
            throw new RuntimeException($reason);
        }

        if (! in_array($item->slug, self::CATALOG, true)) {
            throw new RuntimeException('Los patrocinadores no pueden enviar ese objeto.');
        }

        $afterRound = $game->revealed_round;
        $state = $game->rounds()->where('number', $afterRound)->first()->state;
        $recipient = collect($state['tributes'])->firstWhere('name', $tributeName);

        if ($recipient === null || $recipient['hp'] <= 0) {
            throw new RuntimeException("{$tributeName} no sigue con vida: solo se puede patrocinar a los vivos.");
        }

        return DB::transaction(function () use ($game, $afterRound, $tributeName, $item) {
            $game->gifts()->create(['after_round' => $afterRound, 'tribute_name' => $tributeName, 'item_id' => $item->id]);

            // Volvemos a aplicar TODOS los regalos de esta ronda: el estado guardado no incluye los anteriores
            $gifts = $game->gifts()->where('after_round', $afterRound)->with('item')->orderBy('id')->get();

            return $this->resumeGame->handle($game, $afterRound, function (Simulation $simulation) use ($gifts) {
                foreach ($gifts as $gift) {
                    $tribute = collect($simulation->tributes())->first(fn (Tribute $t) => $t->name === $gift->tribute_name);
                    $tribute->addItem($gift->item);

                    $simulation->announce(new EventResult(
                        text: "Un patrocinador ha enviado [{$gift->item->name}] a {$tribute->name}.",
                        participants: [],
                        gift: true,
                    ));
                }
            });
        });
    }
}
