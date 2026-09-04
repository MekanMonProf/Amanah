<?php

namespace App\Support;

/**
 * Normalise un numéro de téléphone vers une forme canonique unique (format
 * international E.164 : « + » suivi de l'indicatif pays), pour que la même
 * personne ne soit jamais traitée comme deux numéros différents selon qu'elle
 * a saisi « 771234567 », « 00221771234567 » ou « +221771234567 ».
 *
 * Utilisée partout où un numéro sert d'identifiant (connexion portail) ou de
 * clé de comparaison (détection de doublon) : création/modification d'un
 * dossier investisseur ou gestionnaire, import, création d'accès portail,
 * connexion.
 *
 * Beaucoup d'investisseurs ont un numéro étranger (France, USA...). Sans
 * indicatif explicite (+33, 001...), impossible de deviner le pays avec
 * certitude — un numéro local français à 10 chiffres (ex: 0612345678)
 * ressemble à beaucoup d'autres formats nationaux. On ne devine donc
 * l'indicatif QUE pour le Sénégal (9 chiffres, préfixe mobile reconnu :
 * Orange 77/78, Free 76, Expresso 70/75), qui est le cas très majoritaire
 * de la plateforme. Un numéro étranger sans indicatif est nettoyé (espaces,
 * tirets retirés) mais laissé tel quel plutôt que de risquer un mauvais pays.
 */
class Telephone
{
    protected const PREFIXES_MOBILES_SENEGAL = ['70', '75', '76', '77', '78'];

    public static function normaliser(?string $valeur): ?string
    {
        $valeur = trim((string) $valeur);

        if ($valeur === '') {
            return null;
        }

        $aUnPlus = str_starts_with($valeur, '+');
        $chiffres = preg_replace('/\D+/', '', $valeur);

        if ($chiffres === '' || $chiffres === null) {
            return null;
        }

        // Indicatif deja explicite ("+221...", "+33...") : on le respecte tel quel.
        if ($aUnPlus) {
            return '+' . $chiffres;
        }

        // "00" est le prefixe d'acces international standard, strictement equivalent
        // a "+" (ex: 00221771234567 == +221771234567).
        if (str_starts_with($chiffres, '00') && strlen($chiffres) > 4) {
            return '+' . substr($chiffres, 2);
        }

        // Numero local senegalais (9 chiffres, prefixe mobile reconnu) : indicatif +221
        // implicite, seul cas ou l'on complete automatiquement le pays.
        if (strlen($chiffres) === 9 && in_array(substr($chiffres, 0, 2), static::PREFIXES_MOBILES_SENEGAL, true)) {
            return '+221' . $chiffres;
        }

        // Numero etranger sans indicatif explicite (ex: 0612345678) : le pays n'est
        // pas devine, mais la forme est stable pour les comparaisons futures — au
        // moins les variantes d'espacement/ponctuation du meme numero se retrouvent.
        return $chiffres;
    }

    /** True si les deux valeurs, une fois normalisees, designent le meme numero. */
    public static function identiques(?string $a, ?string $b): bool
    {
        $a = static::normaliser($a);
        $b = static::normaliser($b);

        return $a !== null && $a === $b;
    }
}
