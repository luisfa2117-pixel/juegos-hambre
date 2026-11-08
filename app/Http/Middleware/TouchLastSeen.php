<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Apunta cuándo se ha visto por última vez a cada usuario, para saber qué invitados ya no vuelven.
 * Como mucho una vez por hora: guardar en cada petición sería escribir en la base de datos sin necesidad.
 */
class TouchLastSeen
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && ($user->last_seen_at === null || $user->last_seen_at->lt(now()->subHour()))) {
            $user->last_seen_at = now();
            $user->saveQuietly();   // sin disparar eventos del modelo ni tocar updated_at
        }

        return $next($request);
    }
}
