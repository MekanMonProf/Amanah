<?php

namespace App\Support;

/**
 * Libellés français des valeurs d'énumération stockées en base.
 *
 * Les colonnes gardent leur valeur technique — `benefice`, `achat_action` — qui
 * ne bouge pas d'une langue à l'autre et sert au tri comme aux exports. Ce qui
 * s'affiche passe par ici, puis par __() : les vues faisaient jusqu'ici un
 * simple ucfirst(), ce qui laissait « Benefice » sans accent en français et
 * intraduisible ailleurs.
 *
 * Les documents PDF n'utilisent pas ces libellés via __() : ils restent en
 * français, conformément à la règle posée pour toute la papeterie.
 */
class Libelles
{
    private const CATEGORIES = [
        'commercial' => 'Commercial',
        'waqf' => 'Waqf',
    ];

    private const TYPES_ACHAT = [
        'initial' => 'Initial',
        'benefice' => 'Bénéfice',
        'rajout' => 'Rajout',
        'complement' => 'Complément',
        'present' => 'Présent',
    ];

    private const TYPES_PRESENT = [
        'memoire' => 'En mémoire de',
        'honneur' => "En l'honneur de",
    ];

    private const TYPES_DON = [
        'actions' => "Don d'actions",
        'solde' => 'Don sur solde',
    ];

    /**
     * Les actions du journal d'audit.
     *
     * Le journal stocke le verbe technique écrit par l'écran qui a agi. L'affichage
     * se contentait d'y remplacer les tirets bas par des espaces, ce qui donnait
     * « declaration deces » : sans accent, sans majuscule, et impossible à
     * traduire. La colonne ne change pas ; seul le rendu passe par ici.
     */
    private const ACTIONS_AUDIT = [
        'creation' => 'Création',
        'modification' => 'Modification',
        'suppression' => 'Suppression',
        'import' => 'Import',
        'ajustement' => 'Ajustement',
        'paiement' => 'Paiement',
        'complement_financier' => 'Versement complémentaire',
        'don' => 'Don',
        'distribution_dividendes' => 'Distribution de dividendes',
        'correction_bareme' => 'Correction de barème',
        'transfert_gestionnaire' => 'Transfert de gestionnaire',
        'reassignation_masse' => 'Réassignation en masse',
        'creation_acces_portail' => "Création d'un accès au portail",
        'reinitialisation_mdp' => 'Réinitialisation du mot de passe',
        'activation_2fa' => 'Activation de la double authentification',
        'desactivation_2fa' => 'Désactivation de la double authentification',
        'activation' => 'Activation',
        'reactivation' => 'Réactivation',
        'desactivation' => 'Désactivation',
        'declaration_deces' => 'Déclaration de décès',
        'designation_mandataire_succession' => 'Désignation du mandataire',
        'reglement_succession' => 'Règlement de succession',
        'versement_succession' => 'Versement de succession',
    ];

    private const ENTITES_AUDIT = [
        'investisseur' => 'Investisseur',
        'gestionnaire' => 'Gestionnaire',
        'utilisateur' => 'Utilisateur',
        'compte_investissement' => "Compte d'investissement",
        'achat' => "Achat d'actions",
        'radiation' => 'Radiation',
        'bareme_dividende' => 'Barème de dividende',
    ];

    private const TYPES_ECRITURE = [
        'dividende' => 'Dividende',
        'achat_action' => "Achat d'action",
        'paiement' => 'Paiement',
        'versement_complementaire' => 'Versement complémentaire',
        'ajustement' => 'Ajustement',
        'radiation' => 'Radiation',
        'don_sortant' => 'Don sortant',
        'don_entrant' => 'Don entrant',
    ];

    public static function categorie(?string $valeur): string
    {
        return self::resoudre(self::CATEGORIES, $valeur);
    }

    public static function typeAchat(?string $valeur): string
    {
        return self::resoudre(self::TYPES_ACHAT, $valeur);
    }

    public static function typeEcriture(?string $valeur): string
    {
        return self::resoudre(self::TYPES_ECRITURE, $valeur);
    }

    public static function typePresent(?string $valeur): string
    {
        return self::resoudre(self::TYPES_PRESENT, $valeur);
    }

    public static function typeDon(?string $valeur): string
    {
        return self::resoudre(self::TYPES_DON, $valeur);
    }

    public static function actionAudit(?string $valeur): string
    {
        return self::resoudre(self::ACTIONS_AUDIT, $valeur);
    }

    public static function entiteAudit(?string $valeur): string
    {
        return self::resoudre(self::ENTITES_AUDIT, $valeur);
    }

    /**
     * Une valeur inconnue — colonne enrichie plus tard, reprise d'un import —
     * est rendue lisible plutôt que masquée : mieux vaut « Nouveau type » à
     * l'écran qu'une case vide qui ferait croire à une donnée manquante.
     */
    private static function resoudre(array $table, ?string $valeur): string
    {
        if ($valeur === null || $valeur === '') {
            return '—';
        }

        return $table[$valeur] ?? \Illuminate\Support\Str::ucfirst(str_replace('_', ' ', $valeur));
    }
}
