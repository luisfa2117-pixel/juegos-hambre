<?php

namespace App\Enums;

enum Gender: string
{
    case Male = 'male';
    case Female = 'female';

    /** Cómo se muestra en la web */
    public function label(): string
    {
        return match ($this) {
            self::Male => 'Hombre',
            self::Female => 'Mujer',
        };
    }
}
