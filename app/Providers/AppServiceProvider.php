<?php

namespace App\Providers;

use App\Contracts\EnvoyeurSms;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Fournisseur SMS interchangeable (voir config/sms.php et App\Contracts\EnvoyeurSms) —
        // 'journal' par défaut tant qu'aucun fournisseur n'est branché.
        $this->app->bind(EnvoyeurSms::class, function () {
            $pilote = config('sms.driver');
            $classe = config("sms.drivers.{$pilote}");

            abort_unless($classe, 500, "Fournisseur SMS '{$pilote}' introuvable dans config/sms.php.");

            return $this->app->make($classe);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        /*
         * Interdit en production les commandes qui vident la base.
         *
         * AMANAH partage sa base avec l'application qui existait avant elle :
         * les deux jeux de tables cohabitent sans se chevaucher, mais
         * `migrate:fresh`, `migrate:refresh`, `migrate:reset` et `db:wipe` ne
         * font pas le tri — ils suppriment TOUTES les tables de la base, y
         * compris les quatorze qui ne nous appartiennent pas.
         *
         * Une seule commande lancée par reflexe, et l'autre application n'a
         * plus ni utilisateurs ni releves. La barriere coute une ligne ; la
         * remise en etat coûterait une restauration de sauvegarde, en esperant
         * qu'il y en ait une.
         *
         * En local, rien n'est bloque : c'est la que ces commandes servent.
         */
        DB::prohibitDestructiveCommands($this->app->isProduction());
    }
}
