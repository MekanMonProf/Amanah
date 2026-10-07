<?php

namespace App\Support;

use App\Models\DemandeSupport;
use App\Models\ParametreSociete;
use App\Models\User;

/**
 * Le message WhatsApp qui accompagne une demande de support.
 *
 * Comme pour les identifiants (voir MessageWhatsapp), la plateforme n'envoie
 * rien elle-même : elle prépare un lien wa.me avec le message déjà écrit, et
 * c'est la personne qui appuie sur envoyer, depuis son propre compte. Aucun
 * compte Meta Business, aucun modèle à faire approuver, aucun coût par message
 * — en contrepartie, la plateforme ne peut pas attester que le message est
 * parti. C'est pour cela que la demande est enregistrée avant : WhatsApp
 * prévient, la base garde.
 *
 * Le destinataire dépend de qui demande. Un investisseur bloqué doit joindre
 * son gestionnaire, qui connaît son dossier ; un membre de l'équipe doit
 * joindre le support de la plateforme. Envoyer les deux au même endroit ferait
 * remonter au support des questions auxquelles le gestionnaire répond en une
 * phrase.
 */
class MessageSupport
{
    /**
     * À qui cette personne doit écrire.
     *
     * @return array{nom: ?string, numero: ?string, email: ?string, estGestionnaire: bool}
     */
    public static function destinataire(User $utilisateur): array
    {
        if ($utilisateur->role === 'investisseur') {
            $gestionnaire = $utilisateur->investisseurLie?->gestionnaire?->user;

            // Un dossier sans gestionnaire assigné ne doit pas laisser
            // l'investisseur sans recours : il retombe sur le support.
            if ($gestionnaire) {
                return [
                    'nom' => trim($gestionnaire->prenom . ' ' . $gestionnaire->nom),
                    'numero' => $gestionnaire->whatsapp ?: $gestionnaire->telephone,
                    'email' => $gestionnaire->email,
                    'estGestionnaire' => true,
                ];
            }
        }

        $reglages = ParametreSociete::actuel();

        return [
            'nom' => null,
            'numero' => $reglages->numeroWhatsapp(),
            'email' => $reglages->valeur('email'),
            'estGestionnaire' => false,
        ];
    }

    /** Le lien à ouvrir, ou null si le destinataire n'a aucun numéro. */
    public static function lien(DemandeSupport $demande, User $utilisateur): ?string
    {
        $destinataire = self::destinataire($utilisateur);

        if (! $destinataire['numero']) {
            return null;
        }

        return 'https://wa.me/' . ltrim($destinataire['numero'], '+')
            . '?text=' . rawurlencode(self::texte($demande, $utilisateur));
    }

    /**
     * Le texte du message.
     *
     * Il porte le numéro de la demande pour que les deux bouts parlent de la
     * même chose : la conversation WhatsApp et la ligne enregistrée.
     */
    public static function texte(DemandeSupport $demande, User $utilisateur): string
    {
        $lignes = [
            __("Bonjour, j'ai une demande sur la plateforme :application.", [
                'application' => config('app.name', 'Amanah'),
            ]),
            '',
            __("Demande n° :numero — :categorie", [
                'numero' => $demande->id,
                'categorie' => __($demande->libelleCategorie()),
            ]),
            $demande->sujet,
            '',
            $demande->message,
            '',
            __("De la part de :nom.", [
                'nom' => trim($utilisateur->prenom . ' ' . $utilisateur->nom),
            ]),
        ];

        return implode("\n", $lignes);
    }

    /** Le lien mailto équivalent, quand le destinataire a une adresse. */
    public static function lienEmail(DemandeSupport $demande, User $utilisateur): ?string
    {
        $destinataire = self::destinataire($utilisateur);

        if (! $destinataire['email']) {
            return null;
        }

        return 'mailto:' . $destinataire['email']
            . '?subject=' . rawurlencode(__("Demande n° :numero — :sujet", [
                'numero' => $demande->id,
                'sujet' => $demande->sujet,
            ]))
            . '&body=' . rawurlencode(self::texte($demande, $utilisateur));
    }
}
