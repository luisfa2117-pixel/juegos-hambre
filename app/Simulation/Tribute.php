<?php

namespace App\Simulation;

use App\Enums\Gender;
use App\Enums\ItemType;
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

    /** @var Tribute[] */
    private array $allies = [];

    public function __construct(
        public readonly string $name,
        public readonly Gender $gender,
        public readonly District $district,
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
        foreach ($this->inventory as $index => $owned) {
            if ($owned->slug === $item->slug) {
                unset($this->inventory[$index]);
                $this->inventory = array_values($this->inventory);

                return;
            }
        }
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

    /** @return Tribute[] */
    public function allies(): array
    {
        return $this->allies;
    }

    public function isAllyOf(Tribute $other): bool
    {
        return in_array($other, $this->allies, true);
    }

    /** Alianza mutua: los dos quedan como aliados del otro. */
    public function befriend(Tribute $other): void
    {
        if ($other === $this || $this->isAllyOf($other)) {
            return;
        }

        $this->allies[] = $other;
        $other->allies[] = $this;
    }

    /** Rompe la alianza por los dos lados. */
    public function breakAllianceWith(Tribute $other): void
    {
        $this->allies = array_values(array_filter($this->allies, fn (Tribute $t) => $t !== $other));
        $other->allies = array_values(array_filter($other->allies, fn (Tribute $t) => $t !== $this));
    }
}
