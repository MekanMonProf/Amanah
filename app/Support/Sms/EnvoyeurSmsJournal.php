<?php

namespace App\Support\Sms;

use App\Contracts\EnvoyeurSms;
use Illuminate\Support\Facades\Log;

/**
 * Implémentation par défaut tant qu'aucun fournisseur SMS n'est choisi/branché :
 * n'envoie rien réellement, écrit dans les logs Laravel pour permettre de tester
 * le reste du flux (OTP, notifications) sans dépendance externe ni coût.
 *
 * À remplacer par une vraie implémentation (Orange SMS API, Africa's Talking...)
 * le jour venu : créer la classe, l'enregistrer dans EnvoyeurSmsServiceProvider,
 * et régler SMS_DRIVER dans .env — aucun appelant n'a besoin de changer.
 */
class EnvoyeurSmsJournal implements EnvoyeurSms
{
    public function envoyer(string $telephone, string $message): bool
    {
        Log::info('[SMS non envoyé — aucun fournisseur configuré]', [
            'telephone' => $telephone,
            'message' => $message,
        ]);

        return true;
    }
}
