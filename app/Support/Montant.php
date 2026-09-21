<?php

namespace App\Support;

/**
 * Formatage des montants, sûr en écriture de droite à gauche.
 *
 * Un « 35 256 CFA » construit avec des espaces ordinaires s'affiche « CFA 256 35 » en
 * arabe : l'espace ordinaire (U+0020) est un caractère neutre pour l'algorithme
 * bidirectionnel d'Unicode, il coupe donc le montant en trois morceaux que le moteur
 * repose de droite à gauche.
 *
 * On sépare les milliers par une espace insécable étroite (U+202F) : visuellement
 * identique, mais elle n'est pas neutre — le montant reste un bloc unique et conserve
 * son ordre dans toutes les langues.
 *
 * Les documents PDF gardent le formatage français d'origine : ils ne sont pas traduits,
 * et DomPDF n'applique de toute façon aucun algorithme bidirectionnel.
 */
class Montant
{
    /** NARROW NO-BREAK SPACE — visuellement une espace fine, mais non réordonnable. */
    public const SEPARATEUR = "\u{202F}";

    public const DEVISE = 'CFA';

    public static function format(float|int|null $valeur, int $decimales = 0): string
    {
        return number_format((float) $valeur, $decimales, ',', self::SEPARATEUR);
    }

    /** Montant suivi de la devise, l'ensemble restant insécable. */
    public static function avecDevise(float|int|null $valeur, int $decimales = 0): string
    {
        return self::format($valeur, $decimales) . self::SEPARATEUR . self::DEVISE;
    }
}
