<?php

namespace App\Console\Commands;

use App\Models\Character;
use App\Simulation\EventPool;
use App\Simulation\Simulation;
use App\Simulation\Tribute;
use Illuminate\Console\Command;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Tu Main.java: simula una partida entera y la muestra en la terminal.
 * Uso: php artisan simulate             (partida al azar)
 *      php artisan simulate --seed=42   (repite siempre la misma partida)
 */
class SimulateCommand extends Command
{
    protected $signature = 'simulate {--seed= : Semilla para repetir exactamente una partida}';

    protected $description = 'Simula una partida completa con los personajes de la base de datos';

    /** Por si algún día los eventos quedan tan suaves que nadie muere */
    private const MAX_ROUNDS = 100;

    public function handle(): int
    {
        // with('district') carga los distritos de todos en una sola consulta
        $tributes = Character::with('district')->get()
            ->map(fn (Character $character) => $character->toTribute())
            ->all();

        if (count($tributes) < 2) {
            $this->error('Hacen falta al menos 2 personajes. Ejecuta: php artisan db:seed');

            return self::FAILURE;
        }

        $seed = $this->option('seed') !== null ? (int) $this->option('seed') : random_int(1, 999_999);

        $simulation = new Simulation(
            $tributes,
            EventPool::fromDatabase(),
            new Randomizer(new Mt19937($seed)),
        );

        $this->info("Semilla: {$seed}   (para repetir esta partida: php artisan simulate --seed={$seed})");
        $this->line('Participan: ' . implode(', ', array_map(fn (Tribute $t) => $t->name, $tributes)));

        while (! $simulation->isOver() && $simulation->round() < self::MAX_ROUNDS) {
            $results = $simulation->runRound();

            $this->newLine();
            $this->line("<options=bold>━━━━━━━━━━ RONDA {$simulation->round()} ━━━━━━━━━━</>");

            foreach ($results as $result) {
                $this->line($result->text);

                foreach ($result->notes as $note) {
                    $this->line("    {$note}");
                }

                foreach ($result->deaths as $name) {
                    $this->line("    <fg=red>💀 {$name} ha muerto.</>");
                }
            }

            $status = array_map(
                fn (Tribute $t) => "{$t->name} ({$t->hp()} vida, {$t->energy()} energía)",
                $simulation->alive(),
            );
            $this->line('<fg=gray>Siguen vivos: ' . implode(' · ', $status) . '</>');
        }

        $this->newLine();
        $winner = $simulation->winner();

        if ($winner !== null) {
            $this->info("🏆 {$winner->name} ({$winner->district->name}) gana los Juegos en {$simulation->round()} rondas.");
        } elseif ($simulation->isOver()) {
            $this->warn('No queda nadie vivo. Todos han muerto.');
        } else {
            $this->warn('Se ha alcanzado el límite de ' . self::MAX_ROUNDS . ' rondas sin ganador.');
        }

        return self::SUCCESS;
    }
}
