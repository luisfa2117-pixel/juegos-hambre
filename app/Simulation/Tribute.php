<?php

namespace App\Simulation;

use App\Enums\Gender;
use App\Enums\ItemType;
use App\Enums\Personality;
use App\Models\District;
use App\Models\Item;

/**
 * Estado de un tributo DURANTE una simulación (el equivalente a tu Jugador.java).
 * Es una clase PHP normal, no un modelo de Eloquent: vive en memoria mientras
 * se simula y no toca la base de datos.
 */
class Tribute
{
    public const MAX_HP = 20;
    public const BASE_ENERGY = 20;
    public const BASE_STRENGTH = 5;

    private int $hp;
    private int $energy;
    private int $maxEnergy;
    private int $strength;

    /** @var Item[] */
    private array $inventory = [];

    /** @var int[] Usos que le quedan a cada objeto de la mochila (mismo índice que $inventory) */
    private array $usesLeft = [];

    /** @var Tribute[] */
    private array $allies = [];

    public function __construct(
        public readonly string $name,
        public readonly Gender $gender,
        public readonly District $district,
        public readonly ?Personality $personality = null,   // null = equilibrado
    ) {
        $this->hp = self::MAX_HP;
        $this->maxEnergy = self::BASE_ENERGY + $district->energy_bonus;
        $this->energy = $this->maxEnergy;
        $this->strength = self::BASE_STRENGTH + $district->strength_bonus;
    }

    // ---- Vida y energía ----

    public function hp(): int
    {
        return $this->hp;
    }

    public function energy(): int
    {
        return $this->energy;
    }

    public function maxEnergy(): int
    {
        return $this->maxEnergy;
    }

    public function strength(): int
    {
        return $this->strength;
    }

    public function isAlive(): bool
    {
        return $this->hp > 0;
    }

    public function isExhausted(): bool
    {
        return $this->energy === 0;
    }

    /**
     * Cambia la vida: negativo = daño, positivo = curación.
     * Si el tributo está agotado (energía 0), el daño se duplica.
     * Devuelve el cambio que se ha aplicado de verdad (puede ser menor por los límites).
     */
    public function changeHp(int $amount): int
    {
        if (! $this->isAlive()) {
            return 0;
        }

        if ($amount < 0 && $this->isExhausted()) {
            $amount *= 2;
        }

        $before = $this->hp;
        $this->hp = max(0, min(self::MAX_HP, $this->hp + $amount));

        // Los muertos dejan de contar en su alianza
        if (! $this->isAlive()) {
            $this->leaveAlliance();
        }

        return $this->hp - $before;
    }

    /**
     * Cambia la energía, siempre entre 0 y la energía máxima del tributo.
     * Devuelve el cambio aplicado de verdad.
     */
    public function changeEnergy(int $amount): int
    {
        if (! $this->isAlive()) {
            return 0;
        }

        $before = $this->energy;
        $this->energy = max(0, min($this->maxEnergy, $this->energy + $amount));

        return $this->energy - $before;
    }

    // ---- Mochila ----

    /** @return Item[] */
    public function inventory(): array
    {
        return $this->inventory;
    }

    public function addItem(Item $item): void
    {
        $this->inventory[] = $item;
        $this->usesLeft[] = max(1, $item->uses ?? 1);
    }

    public function hasItem(Item $item): bool
    {
        foreach ($this->inventory as $owned) {
            if ($owned->slug === $item->slug) {
                return true;
            }
        }

        return false;
    }

    /** Quita UNA unidad del objeto (si tiene dos botiquines, le queda uno). */
    public function removeItem(Item $item): void
    {
        $index = $this->indexOf($item);

        if ($index === null) {
            return;
        }

        array_splice($this->inventory, $index, 1);
        array_splice($this->usesLeft, $index, 1);
    }

    /**
     * Gasta un uso del objeto. Si era el último, desaparece de la mochila.
     * Devuelve true si se ha gastado del todo (para avisar en el log).
     */
    public function useItem(Item $item): bool
    {
        $index = $this->indexOf($item);

        if ($index === null) {
            return false;
        }

        $this->usesLeft[$index]--;

        if ($this->usesLeft[$index] > 0) {
            return false;
        }

        $this->removeItem($item);

        return true;
    }

    /** Usos que le quedan al primer objeto de ese tipo que lleva (0 si no lo lleva) */
    public function usesLeft(Item $item): int
    {
        $index = $this->indexOf($item);

        return $index === null ? 0 : $this->usesLeft[$index];
    }

    private function indexOf(Item $item): ?int
    {
        foreach ($this->inventory as $index => $owned) {
            if ($owned->slug === $item->slug) {
                return $index;
            }
        }

        return null;
    }

    /** El objeto más potente de un tipo, o null si no tiene ninguno. */
    public function bestItemOfType(ItemType $type): ?Item
    {
        $best = null;

        foreach ($this->inventory as $item) {
            if ($item->type === $type && ($best === null || $item->power > $best->power)) {
                $best = $item;
            }
        }

        return $best;
    }

    // ---- Alianzas ----
    // Una alianza es un grupo: todos sus miembros son aliados entre sí.

    /** @return Tribute[] Los aliados (sin contarse a sí mismo) */
    public function allies(): array
    {
        return $this->allies;
    }

    /** @return Tribute[] Toda la alianza, incluido él mismo */
    public function alliance(): array
    {
        return [$this, ...$this->allies];
    }

    public function isAllyOf(Tribute $other): bool
    {
        return in_array($other, $this->allies, true);
    }

    /**
     * Une las alianzas de los dos: si Dean ya iba con Avery y se alía con Noah,
     * quedan Dean, Avery y Noah, todos aliados entre sí.
     */
    public function befriend(Tribute $other): void
    {
        if ($other === $this || $this->isAllyOf($other)) {
            return;
        }

        $members = [...$this->alliance(), ...$other->alliance()];

        foreach ($members as $member) {
            $member->allies = array_values(array_filter($members, fn (Tribute $t) => $t !== $member));
        }
    }

    /** Sale de su alianza: deja de ser aliado de todos y todos dejan de serlo de él. */
    public function leaveAlliance(): void
    {
        foreach ($this->allies as $ally) {
            $ally->allies = array_values(array_filter($ally->allies, fn (Tribute $t) => $t !== $this));
        }

        $this->allies = [];
    }

    // ---- Guardar y continuar ----

    /** Su estado exacto, para poder reconstruirlo con fromState() (al contrario que toArray, que es para mostrar) */
    public function toState(): array
    {
        return [
            'name' => $this->name,
            'gender' => $this->gender->value,
            'district' => $this->district->slug,
            'personality' => $this->personality?->value,
            'hp' => $this->hp,
            'energy' => $this->energy,
            // En el mismo orden que la mochila: el orden importa (qué objeto se usa primero)
            'items' => array_map(fn (Item $item, int $uses) => [$item->slug, $uses], $this->inventory, $this->usesLeft),
            'allies' => array_map(fn (Tribute $ally) => $ally->name, $this->allies),
        ];
    }

    /**
     * Reconstruye un tributo a partir de toState(). Las alianzas se enlazan después,
     * cuando ya existen todos (ver Simulation::fromState y restoreAllies).
     *
     * @param array<string, Item> $items Los objetos por slug
     */
    public static function fromState(array $state, District $district, array $items): self
    {
        $tribute = new self(
            $state['name'],
            Gender::from($state['gender']),
            $district,
            Personality::tryFrom($state['personality'] ?? ''),
        );

        $tribute->hp = $state['hp'];
        $tribute->energy = $state['energy'];

        foreach ($state['items'] as [$slug, $uses]) {
            // Si un objeto se ha borrado de la base de datos desde entonces, se pierde sin más
            if (isset($items[$slug])) {
                $tribute->inventory[] = $items[$slug];
                $tribute->usesLeft[] = $uses;
            }
        }

        return $tribute;
    }

    /**
     * Solo para reconstruir una partida guardada: fija sus aliados tal cual estaban, en el mismo orden.
     *
     * @param Tribute[] $allies
     */
    public function restoreAllies(array $allies): void
    {
        $this->allies = array_values($allies);
    }

    // ---- Para mostrar ----

    /** El estado actual en un array, para guardarlo en la base de datos. */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'hp' => $this->hp,
            'energy' => $this->energy,
            'max_energy' => $this->maxEnergy,
            'alive' => $this->isAlive(),
            'personality' => $this->personality?->label($this->gender),
            // "Pistola (2 usos)" si le queda más de uno
            'items' => array_map(
                fn (Item $item, int $uses) => $uses > 1 ? "{$item->name} ({$uses} usos)" : $item->name,
                $this->inventory,
                $this->usesLeft,
            ),
            'allies' => array_map(fn (Tribute $ally) => $ally->name, $this->allies),
        ];
    }
}
