<?php

namespace App\Console\Commands;

use App\Models\Game;
use App\Services\GameRecorder;
use Illuminate\Console\Command;

/**
 * Vuelve a calcular el resultado de las partidas guardadas a partir de sus rondas.
 * Hace falta una vez después de la migración de game_kills, para que las partidas
 * de antes también cuenten para némesis y rivalidades.
 */
class SummarizeGamesCommand extends Command
{
    protected $signature = 'games:summarize';

    protected $description = 'Recalcula puestos, asesinatos y némesis de todas las partidas guardadas';

    public function handle(GameRecorder $recorder): int
    {
        $count = 0;

        // De 50 en 50, para no cargar todas las partidas en memoria a la vez
        Game::query()->orderBy('id')->chunkById(50, function ($games) use ($recorder, &$count) {
            foreach ($games as $game) {
                $recorder->summarize($game);
                $count++;
            }
        });

        $this->info("{$count} partidas recalculadas.");

        return self::SUCCESS;
    }
}
