<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Un compte désactivé (gestionnaire ou investisseur) ne doit plus pouvoir travailler,
 * y compris s'il était déjà connecté au moment de la désactivation — le blocage à la
 * connexion seul (LoginForm) ne suffit pas pour une session en cours.
 */
class EnsureCompteActif
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->actif) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => 'Ce compte a été désactivé. Contactez un administrateur.',
            ]);
        }

        return $next($request);
    }
}
