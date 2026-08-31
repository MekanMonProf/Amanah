<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifierDeuxFa
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (
            $user
            && $user->deux_fa_actif
            && ! session('deux_fa_verifie')
            && ! $request->routeIs('deux-fa.verifier')
            && ! $request->routeIs('logout')
        ) {
            return redirect()->route('deux-fa.verifier');
        }

        return $next($request);
    }
}
