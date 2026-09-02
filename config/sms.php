<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Fournisseur SMS
    |--------------------------------------------------------------------------
    |
    | 'journal' (par défaut) n'envoie rien réellement — écrit dans les logs.
    | Une fois un fournisseur choisi (Orange SMS API, Africa's Talking...),
    | créer sa classe dans App\Support\Sms, l'ajouter au tableau 'drivers'
    | ci-dessous, et régler SMS_DRIVER dans .env. Aucun code appelant à
    | modifier (voir App\Contracts\EnvoyeurSms).
    |
    */

    'driver' => env('SMS_DRIVER', 'journal'),

    'drivers' => [
        'journal' => \App\Support\Sms\EnvoyeurSmsJournal::class,
    ],

];
