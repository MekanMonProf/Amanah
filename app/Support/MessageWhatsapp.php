<?php

namespace App\Support;

use App\Models\Investisseur;

/**
 * Le message WhatsApp qui transmet ses identifiants à un investisseur.
 *
 * La plupart des investisseurs n'ont pas d'email : jusqu'ici le gestionnaire
 * lisait le mot de passe temporaire à l'écran et le dictait au téléphone. Le lien
 * construit ici ouvre WhatsApp avec le message déjà écrit ; le gestionnaire n'a
 * plus qu'à l'envoyer, depuis son propre compte.
 *
 * Rien n'est envoyé par la plateforme, et c'est volontaire : aucun compte Meta
 * Business, aucun modèle à faire approuver, aucun coût par message. En
 * contrepartie l'envoi reste un geste manuel, et la plateforme ne peut pas
 * attester qu'il a eu lieu. Le jour où un envoi automatique sera nécessaire,
 * c'est le texte ci-dessous qu'il faudra reprendre, pas les écrans.
 *
 * Le mot de passe voyage dans l'URL, donc par le redirecteur de wa.me, et reste
 * ensuite dans l'historique de conversation. Il est temporaire et doit être changé
 * à la première connexion — `doit_changer_mot_de_passe` est posé à la création
 * comme à la réinitialisation — ce qui le rend inutilisable une fois servi.
 */
class MessageWhatsapp
{
    /**
     * Le lien à ouvrir, ou null si le dossier ne porte aucun numéro joignable.
     */
    public static function lienAcces(Investisseur $investisseur, string $motDePasse, bool $nouveauCompte): ?string
    {
        $destinataire = self::numeroDestinataire($investisseur);

        if ($destinataire === null) {
            return null;
        }

        return 'https://wa.me/' . ltrim($destinataire, '+')
            . '?text=' . rawurlencode(self::texteAcces($investisseur, $motDePasse, $nouveauCompte));
    }

    /**
     * Le numéro à qui écrire : le WhatsApp du dossier, à défaut le téléphone.
     *
     * Les deux sont le plus souvent identiques — la case « même numéro » est
     * cochée par défaut à la saisie — mais quand ils diffèrent, c'est que
     * quelqu'un a pris la peine de les distinguer.
     */
    public static function numeroDestinataire(Investisseur $investisseur): ?string
    {
        return Telephone::normaliser($investisseur->whatsapp)
            ?? Telephone::normaliser($investisseur->telephone);
    }

    /**
     * Le corps du message, dans la langue de l'investisseur.
     *
     * Le destinataire n'est pas l'exploitant : le message suit la langue du
     * dossier, et non celle du gestionnaire qui a l'écran sous les yeux.
     *
     * C'est bien le dossier qui fait foi, et non `users.langue` : le message part
     * au moment où l'accès se crée, quand le compte vient tout juste de naître, et
     * la plupart des investisseurs n'auront jamais de compte du tout.
     */
    public static function texteAcces(Investisseur $investisseur, string $motDePasse, bool $nouveauCompte): string
    {
        $langue = Langue::normaliser($investisseur->langue);

        $identifiant = $investisseur->user?->email ?? $investisseur->user?->telephone ?? $investisseur->telephone;

        $salutation = $nouveauCompte
            ? "Assalamou aleykoum :nom, un accès vient d'être créé pour vous sur AMANAH (AND DOX S.A.)."
            : "Assalamou aleykoum :nom, votre mot de passe AMANAH (AND DOX S.A.) vient d'être réinitialisé.";

        return __($salutation, ['nom' => trim($investisseur->prenom . ' ' . $investisseur->nom)], $langue)
            . "\n\n"
            . __("Identifiant : :identifiant", ['identifiant' => $identifiant], $langue) . "\n"
            . __("Mot de passe temporaire : :motdepasse", ['motdepasse' => $motDePasse], $langue) . "\n"
            . __("Connexion : :lien", ['lien' => route('login')], $langue)
            . "\n\n"
            . __("Il vous sera demandé de choisir un nouveau mot de passe dès votre première connexion. Ne communiquez ce message à personne.", [], $langue)
            . self::ligneContact($langue)
            . "

" . __("Barak'ALLAH Fikoum", [], $langue);
    }

    /**
     * La ligne de contact, absente tant qu'aucun numéro de support n'est réglé :
     * mieux vaut pas de contact du tout qu'un numéro que personne ne décroche.
     *
     * La formule de clôture, elle, vient juste après mais ne dépend pas d'elle :
     * un message sans numéro de contact se termine quand même.
     */
    private static function ligneContact(string $langue): string
    {
        $support = Telephone::normaliser(config('societe.whatsapp_support'));

        if ($support === null) {
            return '';
        }

        return "\n\n" . __("Une question ? Écrivez-nous au :contact.", ['contact' => $support], $langue);
    }
}
