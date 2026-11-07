<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Una Gate es un permiso que no depende de un modelo concreto:
        // "¿puede este usuario gestionar los eventos?"
        Gate::define('manage-events', fn (User $user) => $user->is_admin);
    }
}
