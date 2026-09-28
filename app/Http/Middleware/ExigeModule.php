<?php

namespace App\Http\Middleware;

use App\Support\Droits;
use App\Support\Modules;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Remplace le middleware `role:` sur les routes de gestion.
 *
 * Usage dans routes/web.php :
 *   Route::middleware('module:investisseurs')->group(...)          // lecture
 *   Route::middleware('module:investisseurs,ecriture')->group(...)
 *
 * La différence avec `role:` tient en une phrase : la liste des rôles admis
 * n'est plus écrite ici mais lue dans la table des permissions, donc réglable
 * depuis l'écran de paramétrage sans toucher au code.
 *
 * `role:` reste en place pour ce qui ne relève d'aucun module — le portail
 * investisseur, par exemple, dont l'accès ne se paramètre pas.
 */
class ExigeModule
{
    public function handle(Request $request, Closure $next, string $module, string $niveau = Modules::LECTURE): Response
    {
        Droits::exiger($module, $niveau);

        return $next($request);
    }
}
