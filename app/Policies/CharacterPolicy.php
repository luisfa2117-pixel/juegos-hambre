<?php

namespace App\Policies;

use App\Models\Character;
use App\Models\User;

/**
 * Quién puede hacer qué con un personaje. Laravel la encuentra sola por el nombre
 * (Character → CharacterPolicy) y la usa con Gate::authorize('update', $character).
 */
class CharacterPolicy
{
    public function view(User $user, Character $character): bool
    {
        return $character->user_id === $user->id;
    }

    public function update(User $user, Character $character): bool
    {
        return $character->user_id === $user->id;
    }

    public function delete(User $user, Character $character): bool
    {
        return $character->user_id === $user->id;
    }
}
