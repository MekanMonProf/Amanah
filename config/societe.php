<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Numéro WhatsApp de support
    |--------------------------------------------------------------------------
    |
    | Le numéro que la plateforme donne à l'investisseur dans le message qui lui
    | transmet ses identifiants. Un numéro unique pour toute la société, et non
    | celui du gestionnaire : les numéros personnels des gestionnaires n'ont pas
    | à circuler, et un investisseur transféré n'a pas à changer d'interlocuteur.
    |
    | Un mobile sénégalais peut s'écrire tel quel — 771234567 — App\Support\Telephone
    | lui ajoute l'indicatif. Tout autre numéro, fixe compris, doit être donné sous
    | forme internationale (+221338001122 ou 00221338001122), faute de quoi il
    | partira dans le message sans indicatif et sera injoignable depuis l'étranger.
    |
    | Laissé vide, le message est simplement envoyé sans ligne de contact.
    |
    */

    'whatsapp_support' => env('AMANAH_WHATSAPP_SUPPORT'),

];
