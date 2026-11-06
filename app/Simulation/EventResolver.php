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

    /** Categorías en las que la defensa del objetivo reduce el daño (combates directos). */
    private const ARMOR_CATEGORIES = [EventCategory::Duo, EventCategory::Weapon];

    /**
     * @param Tribute[] $participants El primero es {A} (el actor); el resto son {B}, {C}, {D}
     */
    public function apply(GameEvent $event, array $participants): EventResult
    {
        $this->validate($event, $participants);

        $actor = $participants[0];
        $targets = array_slice($participants, 1);
        $notes = [];

        // 1. Consecuencias para el actor {A}
        $actor->changeHp($event->actor_hp);
        $actor->changeEnergy($event->actor_energy);

        // 2. Consecuencias para los objetivos {B}, {C}, {D}
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

        // 3. Objetos: el requerido se gasta, el del descubrimiento se guarda
        if ($event->requiresItem !== null) {
            $actor->removeItem($event->requiresItem);
        }

        if ($event->givesItem !== null) {
            $actor->addItem($event->givesItem);
            $notes[] = "🎒 {$actor->name} guardó [{$event->givesItem->name}] en su mochila.";
        }

        // 4. Alianzas
        if ($event->category === EventCategory::Befriend) {
            $actor->befriend($targets[0]);
            $notes[] = "🤝 {$actor->name} y {$targets[0]->name} ahora son aliados.";
        }

        if ($event->category === EventCategory::Betrayal) {
            $actor->breakAllianceWith($targets[0]);
            $notes[] = "💔 La alianza entre {$actor->name} y {$targets[0]->name} se ha roto.";
        }

        // 5. Muertes
        $deaths = [];
        foreach ($participants as $tribute) {
            if (! $tribute->isAlive()) {
                $deaths[] = $tribute->name;
            }
        }

        // 6. Los supervivientes heridos o agotados usan sus consumibles
        foreach ($participants as $tribute) {
            if ($tribute->isAlive()) {
                array_push($notes, ...$this->useConsumables($tribute));
            }
        }

        return new EventResult($this->formatText($event->text, $participants), $notes, $deaths);
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
        $target->removeItem($armor);

        $note = "🛡️ {$target->name} absorbió {$absorbed} de daño con [{$armor->name}], que quedó inservible.";

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

    /** "{A} disparó a {B}" => "Dean disparó a Avery" */
    private function formatText(string $text, array $participants): string
    {
        $labels = ['{A}', '{B}', '{C}', '{D}'];
        $names = array_map(fn (Tribute $t) => $t->name, $participants);

        return str_replace(array_slice($labels, 0, count($names)), $names, $text);
    }
}
