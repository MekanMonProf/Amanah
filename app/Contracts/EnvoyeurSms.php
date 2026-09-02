<?php

namespace App\Contracts;

/**
 * Interface d'envoi de SMS — interchangeable pour brancher un vrai fournisseur
 * (Orange SMS API, Africa's Talking, Twilio...) une fois choisi, sans toucher au
 * code appelant. Voir App\Support\Sms\EnvoyeurSmsJournal pour l'implémentation
 * par défaut (aucun envoi réel, juste un enregistrement dans les logs).
 */
interface EnvoyeurSms
{
    /**
     * @param  string  $telephone  Numéro au format local (ex: 771234567) ou international.
     * @param  string  $message  Contenu du SMS (garder court — la plupart des fournisseurs
     *                           facturent par tranche de 160 caractères).
     * @return bool  true si le message a été transmis au fournisseur avec succès.
     */
    public function envoyer(string $telephone, string $message): bool;
}
