<?php

namespace App\Policies;

use App\Models\Game;
use App\Models\User;

class GamePolicy
{
    public function view(User $user, Game $game): bool
    {
        return $game->user_id === $user->id;
    }

    /** Solo el dueño de la partida puede patrocinar a sus tributos */
    public function sponsor(User $user, Game $game): bool
    {
        return $game->user_id === $user->id;
    }

    public function delete(User $user, Game $game): bool
    {
        return $game->user_id === $user->id;
    }
}
