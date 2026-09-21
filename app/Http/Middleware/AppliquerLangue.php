<?php

namespace App\Http\Middleware;

use App\Support\Langue;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applique la langue d'interface à chaque requête.
 *
 * Priorité : la préférence enregistrée sur le compte, sinon le choix fait en session
 * (écran de connexion, avant toute authentification), sinon le français. La session sert
 * donc les visiteurs non connectés ; dès qu'un compte est identifié, c'est lui qui décide,
 * pour qu'un utilisateur retrouve sa langue depuis n'importe quel poste.
 */
class AppliquerLangue
{
    public function handle(Request $request, Closure $next): Response
    {
        $code = Auth::check()
            ? Auth::user()->langue
            : session('langue');

        app()->setLocale(Langue::normaliser($code));

        return $next($request);
    }
}
