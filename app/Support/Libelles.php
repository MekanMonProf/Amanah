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
