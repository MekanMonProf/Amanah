<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    /**
     * Usage dans routes/web.php :
     *   Route::middleware('role:direction,administrateur')->group(...)
     *
     * Rôles disponibles : direction, administrateur, gestionnaire, lecture
     * (voir section 4 du document de vision — hiérarchie fonctionnelle).
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! in_array($user->role, $roles, true)) {
            abort(403, "Vous n'avez pas les droits nécessaires pour accéder à cette page.");
        }

        return $next($request);
    }
}
