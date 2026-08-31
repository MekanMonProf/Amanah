<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForceChangementMotDePasse
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->doit_changer_mot_de_passe && ! $request->routeIs('mot-de-passe.changer-obligatoire')) {
            return redirect()->route('mot-de-passe.changer-obligatoire');
        }

        return $next($request);
    }
}
