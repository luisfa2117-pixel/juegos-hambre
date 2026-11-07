<?php

namespace App\Simulation;

use App\Enums\EventCategory;
use App\Enums\ItemType;
use App\Models\GameEvent;
use InvalidArgumentException;

/**
 * Aplica un evento a sus participantes: vida, energía, objetos y alianzas.
 * Sustituye a los aplicar() de EventoGenerico, EventoCombate y EventoDescubrimiento.
 *
 * No decide QUÉ evento ocurre (eso será la ronda, en la parte 2.3):
 * recibe un evento ya elegido y lo ejecuta.
 */
class EventResolver
{
    /** Con menos vida que esto, el tributo usa un objeto de curación si lo tiene. */
    public const HEAL_THRESHOLD = 10;

    /** Con menos energía que esto, el tributo usa un objeto de energía si lo tiene. */
    public const ENERGY_THRESHOLD = 5;

    /** Peleas de dos en las que, si uno muere, le ha matado el otro */
    private const HOSTILE_PAIRS = [EventCategory::Duo, EventCategory::Weapon, EventCategory::Betrayal];

    /** Categorías en las que la defensa del objetivo reduce el daño (combates directos). */
    private const ARMOR_CATEGORIES = [EventCategory::Duo, EventCategory::Weapon, EventCategory::AllyAttack];

    /**
     * @param Tribute[] $participants El primero es {A} (el actor); el resto son {B}, {C}, {D}
     */
    public function apply(GameEvent $event, array $participants): EventResult
    {
        $this->validate($event, $participants);

        // Quien actúa: normalmente {A}; si dos aliados atacan juntos, {A} y {B}
        $actorCount = $event->category->actors();
        $actors = array_slice($participants, 0, $actorCount);
        $targets = array_slice($participants, $actorCount);
        $actor = $actors[0];
        $notes = [];

        // 1. Consecuencias para quien actúa
        foreach ($actors as $attacker) {
            $attacker->changeHp($event->actor_hp);
            $attacker->changeEnergy($event->actor_energy);
        }

        // 2. Consecuencias para los demás
        foreach ($targets as $target) {
            $hpChange = $event->target_hp;

            if ($hpChange < 0) {
                if ($event->category === EventCategory::Weapon) {
                    $hpChange -= $this->strengthBonus($actor);
                }

                if (in_array($event->category, self::ARMOR_CATEGORIES, true)) {
                    [$hpChange, $armorNote] = $this->applyArmor($target, $hpChange);

                    if ($armorNote !== null) {
                        $notes[] = $armorNote;
                    }
                }
            }

            $target->changeHp($hpChange);
            $target->changeEnergy($event->target_energy);
        }

        // 3. Objetos: el requerido gasta un uso, el del descubrimiento se guarda
        if ($event->requiresItem !== null && $actor->useItem($event->requiresItem)) {
            // Las armas avisan cuando se rompen; un botiquín gastado no hace falta anunciarlo
            if ($event->requiresItem->type === ItemType::Weapon) {
                $notes[] = "🔧 [{$event->requiresItem->name}] de {$actor->name} ya no sirve.";
            }
        }

        if ($event->givesItem !== null) {
            $actor->addItem($event->givesItem);
            $notes[] = "🎒 {$actor->name} guardó [{$event->givesItem->name}] en su mochila.";
        }

        // 4. Alianzas
        if ($event->category === EventCategory::Befriend) {
            $actor->befriend($targets[0]);
            $notes[] = $this->allianceNote($actor);
        }

        if ($event->category === EventCategory::Betrayal) {
            $actor->leaveAlliance();
            $notes[] = "💔 {$actor->name} rompe con su alianza y va por su cuenta.";
        }

        // 5. Muertes, y quién las ha causado
        $deaths = [];
        $kills = [];
        foreach ($participants as $tribute) {
            if (! $tribute->isAlive()) {
                $deaths[] = $tribute->name;
                $kills[] = [
                    'victim' => $tribute->name,
                    'killers' => array_map(fn (Tribute $t) => $t->name, $this->killersOf($tribute, $event, $actors, $targets)),
                ];
            }
        }

        // 6. Los supervivientes heridos o agotados usan sus consumibles
        foreach ($participants as $tribute) {
            if ($tribute->isAlive()) {
                array_push($notes, ...$this->useConsumables($tribute));
            }
        }

        return new EventResult(
            text: TextFormatter::format($event->text, $participants),
            notes: $notes,
            deaths: $deaths,
            participants: array_map(fn (Tribute $t) => $t->name, $participants),
            kills: $kills,
        );
    }

    /**
     * Quién ha matado a $victim en este evento. Vacío si ha sido un accidente.
     *
     * - Peleas de dos, armas y traiciones: el otro.
     * - Aliados atacan juntos: si muere la víctima, los dos atacantes; si muere un atacante, la víctima.
     * - Grupos de 3 o 4: {A}, pero solo si sale mejor parado que los demás (lanza la bomba).
     *   Si todos reciben lo mismo (un incendio, una trampa), es un accidente.
     *
     * @param Tribute[] $actors
     * @param Tribute[] $targets
     * @return Tribute[]
     */
    private function killersOf(Tribute $victim, GameEvent $event, array $actors, array $targets): array
    {
        $isActor = in_array($victim, $actors, true);

        if (in_array($event->category, self::HOSTILE_PAIRS, true)) {
            return $isActor ? [$targets[0]] : [$actors[0]];
        }

        if ($event->category === EventCategory::AllyAttack) {
            return $isActor ? [$targets[0]] : $actors;
        }

        if (in_array($event->category, [EventCategory::Trio, EventCategory::Quartet], true)) {
            return ! $isActor && $event->actor_hp > $event->target_hp ? [$actors[0]] : [];
        }

        return [];
    }

    /** Comprueba que el evento se puede aplicar a estos participantes. */
    private function validate(GameEvent $event, array $participants): void
    {
        if (count($participants) !== $event->participants) {
            throw new InvalidArgumentException(
                "El evento necesita {$event->participants} participantes y ha recibido " . count($participants)
            );
        }

        foreach ($participants as $tribute) {
            if (! $tribute->isAlive()) {
                throw new InvalidArgumentException("{$tribute->name} está muerto y no puede participar");
            }
        }

        if ($event->requiresItem !== null && ! $participants[0]->hasItem($event->requiresItem)) {
            throw new InvalidArgumentException(
                "{$participants[0]->name} no tiene [{$event->requiresItem->name}], que este evento requiere"
            );
        }
    }

    /** Daño extra por fuerza: lo que el tributo tiene por encima de la fuerza base. */
    private function strengthBonus(Tribute $actor): int
    {
        return max(0, $actor->strength() - Tribute::BASE_STRENGTH);
    }

    /**
     * Si el objetivo tiene defensa, absorbe parte del daño y se rompe.
     * La defensa nunca cura: como mucho deja el daño en 0.
     *
     * @return array{0: int, 1: ?string} [el cambio de vida final, la nota para el log o null]
     */
    private function applyArmor(Tribute $target, int $hpChange): array
    {
        $armor = $target->bestItemOfType(ItemType::Armor);

        if ($armor === null) {
            return [$hpChange, null];
        }

        $absorbed = min($armor->power, -$hpChange);
        $broken = $target->useItem($armor);

        $note = "🛡️ {$target->name} absorbió {$absorbed} de daño con [{$armor->name}]"
            . ($broken ? ', que quedó inservible.' : '.');

        return [$hpChange + $absorbed, $note];
    }

    /**
     * Lo que dejaste comentado en ProcesarIndividual: si está herido o agotado
     * y lleva el objeto adecuado, lo usa.
     *
     * @return string[] Notas para el log
     */
    private function useConsumables(Tribute $tribute): array
    {
        $notes = [];

        if ($tribute->hp() < self::HEAL_THRESHOLD) {
            $medkit = $tribute->bestItemOfType(ItemType::Heal);

            if ($medkit !== null) {
                $healed = $tribute->changeHp($medkit->power);
                $tribute->removeItem($medkit);
                $notes[] = "💊 {$tribute->name} usó [{$medkit->name}] y recuperó {$healed} de vida.";
            }
        }

        if ($tribute->energy() < self::ENERGY_THRESHOLD) {
            $drink = $tribute->bestItemOfType(ItemType::Energy);

            if ($drink !== null) {
                $gained = $tribute->changeEnergy($drink->power);
                $tribute->removeItem($drink);
                $notes[] = "⚡ {$tribute->name} usó [{$drink->name}] y recuperó {$gained} de energía.";
            }
        }

        return $notes;
    }

    /** "Dean y Avery ahora son aliados" o, si ya eran más, quién forma la alianza */
    private function allianceNote(Tribute $actor): string
    {
        $alliance = $actor->alliance();

        if (count($alliance) === 2) {
            $allies = TextFormatter::gendered($alliance, 'aliados', 'aliadas');

            return '🤝 ' . TextFormatter::names($alliance) . " ahora son {$allies}.";
        }

        return '🤝 La alianza ahora la forman ' . TextFormatter::names($alliance) . '.';
    }
}
