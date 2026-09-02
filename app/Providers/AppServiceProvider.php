<?php

namespace App\Providers;

use App\Contracts\EnvoyeurSms;
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
        //
    }
}
