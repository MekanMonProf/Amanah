<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
    'role' => \App\Http\Middleware\EnsureRole::class,
    'doit.changer.mdp' => \App\Http\Middleware\ForceChangementMotDePasse::class,
    'deux.fa' => \App\Http\Middleware\VerifierDeuxFa::class,

]);

        // Un compte desactive doit etre deconnecte immediatement, meme si sa session
        // etait deja ouverte au moment de la desactivation (voir EnsureCompteActif).
        $middleware->web(append: [
            \App\Http\Middleware\EnsureCompteActif::class,
            // Applique la langue d interface avant le rendu (voir AppliquerLangue).
            \App\Http\Middleware\AppliquerLangue::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
