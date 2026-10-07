<?php

namespace App\Support;

use App\Models\EcritureCompteFinancier;
use Illuminate\Support\Facades\URL;

/**
 * Le reçu d'une opération, et le message qui le porte à l'investisseur.
 *
 * L'écriture est l'unité commune : versement complémentaire, paiement, achat sur
 * solde, don, ajustement, capital d'une radiation — tout mouvement d'argent en
 * produit une. Un seul reçu couvre donc tout, là où un document par type
 * d'opération aurait multiplié les gabarits pour dire la même chose.
 *
 * Le reçu ne remplace pas les attestations : celles-ci sont les pièces formelles
 * d'un événement — un achat d'actions, une radiation, une succession — quand le
 * reçu est le billet qui dit « voici ce qui vient de se passer sur votre compte ».
 *
 * Le lien porte sa propre signature et s'ouvre sans compte : la plupart des
 * investisseurs n'ont pas d'accès au portail, et leur demander d'en ouvrir un
 * pour lire un reçu reviendrait à ne pas le leur envoyer.
 */
class Recu
{
    /** Le lien signé vers le PDF, valable le temps réglé dans config/societe.php. */
    public static function lienSigne(EcritureCompteFinancier $ecriture): string
    {
        return URL::temporarySignedRoute(
            'recu.public',
            now()->addDays(max(1, (int) config('societe.validite_recu_jours', 30))),
            ['ecriture' => $ecriture->id],
        );
    }

    /**
     * Le lien WhatsApp pré-rempli, ou null si le dossier ne porte aucun numéro.
     */
    public static function lienWhatsapp(EcritureCompteFinancier $ecriture): ?string
    {
        $investisseur = $ecriture->compte?->investisseur;

        if (! $investisseur) {
            return null;
        }

        $destinataire = MessageWhatsapp::numeroDestinataire($investisseur);

        if ($destinataire === null) {
            return null;
        }

        return 'https://wa.me/' . ltrim($destinataire, '+')
            . '?text=' . rawurlencode(self::texte($ecriture));
    }

    /**
     * Le corps du message, dans la langue de l'investisseur.
     *
     * Le montant et le sens sont dits en toutes lettres avant le lien : un
     * message qui ne contiendrait qu'une adresse ressemblerait à une tentative
     * d'hameçonnage, et personne ne l'ouvrirait.
     */
    public static function texte(EcritureCompteFinancier $ecriture): string
    {
        $investisseur = $ecriture->compte->investisseur;
        $langue = Langue::normaliser($investisseur->langue);
        $montant = abs((float) $ecriture->montant);

        $entree = (float) $ecriture->montant >= 0;

        return __("Assalamou aleykoum :nom,", [
            'nom' => trim($investisseur->prenom . ' ' . $investisseur->nom),
        ], $langue)
            . "\n\n"
            . ($entree
                ? __("Nous confirmons l'enregistrement de :montant sur votre compte :compte, au titre de : :nature.", [
                    'montant' => Montant::avecDevise($montant),
                    'compte' => $ecriture->compte->numero_compte,
                    'nature' => __(Libelles::typeEcriture($ecriture->type_ecriture), [], $langue),
                ], $langue)
                : __("Nous confirmons le versement de :montant depuis votre compte :compte, au titre de : :nature.", [
                    'montant' => Montant::avecDevise($montant),
                    'compte' => $ecriture->compte->numero_compte,
                    'nature' => __(Libelles::typeEcriture($ecriture->type_ecriture), [], $langue),
                ], $langue))
            . "\n"
            . __("Date : :date", ['date' => $ecriture->date_ecriture->format('d/m/Y')], $langue) . "\n"
            . __("Solde après opération : :solde", [
                'solde' => Montant::avecDevise((float) $ecriture->solde_apres),
            ], $langue)
            . "\n\n"
            . __("Votre reçu : :lien", ['lien' => self::lienSigne($ecriture)], $langue)
            . self::ligneContact($langue)
            . "\n\n"
            . __("Barak'ALLAH Fikoum", [], $langue);
    }

    private static function ligneContact(string $langue): string
    {
        // Le numéro réglé dans « Paramétrage → La société » ; à défaut, celui de
        // config/societe.php, qui s'appliquait avant que l'écran n'existe.
        $support = \App\Models\ParametreSociete::actuel()->numeroWhatsapp();

        return $support === null
            ? ''
            : "\n\n" . __("Une question ? Écrivez-nous au :contact.", ['contact' => $support], $langue);
    }
}
