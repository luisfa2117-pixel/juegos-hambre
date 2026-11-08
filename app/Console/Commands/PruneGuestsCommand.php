<?php

namespace App\Console\Commands;

use App\Actions\DeleteAccount;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Borra las cuentas de invitado que llevan días sin usarse.
 * Se ejecuta solo cada día (ver routes/console.php), si el servidor tiene el planificador activado.
 */
class PruneGuestsCommand extends Command
{
    protected $signature = 'guests:prune {--days= : Días sin entrar (por defecto, los de config/juegos.php)}';

    protected $description = 'Borra las cuentas de invitado que no se usan';

    public function handle(DeleteAccount $deleteAccount): int
    {
        $days = (int) ($this->option('days') ?? config('juegos.guest_days'));
        $deleted = 0;

        // lazyById: los va trayendo de 1.000 en 1.000, sin cargar todos en memoria a la vez
        User::where('is_guest', true)
            ->where(fn ($query) => $query->whereNull('last_seen_at')->orWhere('last_seen_at', '<', now()->subDays($days)))
            ->lazyById()
            ->each(function (User $user) use ($deleteAccount, &$deleted) {
                $deleteAccount->handle($user);
                $deleted++;
            });

        $this->info("Cuentas de invitado borradas: {$deleted}.");

        return self::SUCCESS;
    }
}
