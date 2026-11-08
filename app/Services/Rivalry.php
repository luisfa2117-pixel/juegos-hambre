<?php

namespace App\Services;

use App\Models\Character;

/** $killer ha matado $times veces a $victim */
final readonly class Rivalry
{
    public function __construct(
        public Character $killer,
        public Character $victim,
        public int $times,
    ) {}
}
