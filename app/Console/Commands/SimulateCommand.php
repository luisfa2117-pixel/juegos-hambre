<?php

namespace App\Console\Commands;

use App\Enums\EventPhase;
use App\Enums\GameRule;
use App\Enums\ItemType;
use App\Models\Item;
use App\Models\User;
use App\Simulation\EventPool;
use App\Simulation\GameRules;
use App\Simulation\Simulation;
use App\Simulation\Tribute;
use Illuminate\Console\Command;

/**
 * Tu Main.java: simula una partida entera y la muestra en la terminal.
 * Uso: php artisan simulate             (partida al azar)
 *      php artisan simulate --seed=42   (repite siempre la misma partida)
 *      php artisan simulate --regla=armed --regla=half_life
 *      php artisan simulate --vasallaje
 */
class SimulateCommand extends Command
{
    protected $signature = 'simulate {--seed= : Semilla para repetir exactamente una partida} {--email=demo@juegos.test : De quién son los personajes}
        {--regla=* : Reglas especiales (no_alliances, traitors, armed, half_life, catastrophes, scarcity)}
        {--vasallaje : Vasallaje de los 25: dos reglas al azar}';

    protected $description = 'Simula una partida completa con los personajes de la base de datos';

    /** Por si algún día los eventos quedan tan suaves que nadie muere */
    private const MAX_ROUNDS = 100;

    public function handle(): int
    {
        $user = User::where('email', $this->option('email'))->first();

        if ($user === null) {
            $this->error("No hay ninguna cuenta con el correo {$this->option('email')}.");

            return self::FAILURE;
        }

        // with('district') carga los distritos de todos en una sola consulta
        $tributes = $user->characters()->with('district')->get()
            ->map(fn ($character) => $character->toTribute())
            ->all();

        if (count($tributes) < 2) {
            $this->error('Hacen falta al menos 2 personajes. Ejecuta: php artisan db:seed');

            return self::FAILURE;
        }

        $seed = $this->option('seed') !== null ? (int) $this->option('seed') : random_int(1, 999_999);

        $unknown = array_filter($this->option('regla'), fn (string $value) => GameRule::tryFrom($value) === null);
        if ($unknown !== []) {
            $this->error('No existe la regla: '.implode(', ', $unknown));

            return self::FAILURE;
        }

        $rules = $this->option('vasallaje')
            ? new GameRules(GameRule::quell($seed))
            : GameRules::fromValues($this->option('regla'));

        $simulation = new Simulation($tributes, EventPool::fromDatabase(), $seed, $rules);
        $simulation->applyStartingRules(Item::where('type', ItemType::Weapon)->orderBy('id')->get()->all());

        if ($announcement = $rules->announcement($this->option('vasallaje'))) {
            $simulation->announce($announcement);
        }

        $this->info("Semilla: {$seed}   (para repetir esta partida: php artisan simulate --seed={$seed})");
        if (! $rules->isEmpty()) {
            $prefix = $this->option('vasallaje') ? 'Vasallaje de los 25' : 'Reglas';
            $this->warn("{$prefix}: ".implode(', ', array_map(fn (GameRule $rule) => $rule->label(), $rules->all())));
        }
        $this->line('Participan: '.implode(', ', array_map(fn (Tribute $t) => $t->name, $tributes)));

        while (! $simulation->isOver() && $simulation->round() < self::MAX_ROUNDS) {
            $results = $simulation->runRound();

            $this->newLine();
            $title = mb_strtoupper(EventPhase::roundTitle($simulation->round()));
            $this->line("<options=bold>━━━━━━━━━━ {$title} ━━━━━━━━━━</>");

            foreach ($results as $result) {
                if ($result->arena !== null) {
                    $this->warn("⚠ {$result->arena}: {$result->text}");

                    continue;
                }

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
            $this->line('<fg=gray>Siguen vivos: '.implode(' · ', $status).'</>');
        }

        $this->newLine();
        $winner = $simulation->winner();

        if ($winner !== null) {
            $this->info("🏆 {$winner->name} ({$winner->district->name}) gana los Juegos en {$simulation->round()} rondas.");
        } elseif ($simulation->isOver()) {
            $this->warn('No queda nadie vivo. Todos han muerto.');
        } else {
            $this->warn('Se ha alcanzado el límite de '.self::MAX_ROUNDS.' rondas sin ganador.');
        }

        return self::SUCCESS;
    }
}
