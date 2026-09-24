<?php

namespace App\Support;

/**
 * Normalise un numéro de téléphone vers une forme canonique unique (format
 * international E.164 : « + » suivi de l'indicatif pays), pour que la même
 * personne ne soit jamais traitée comme deux numéros différents selon qu'elle
 * a saisi « 771234567 », « 00221771234567 », « 221771234567 » ou
 * « +221771234567 ».
 *
 * Utilisée partout où un numéro sert d'identifiant (connexion portail) ou de
 * clé de comparaison (détection de doublon) : création/modification d'un
 * dossier investisseur ou gestionnaire, import, création d'accès portail,
 * connexion.
 *
 * Beaucoup d'investisseurs ont un numéro étranger, souvent saisi avec
 * l'indicatif collé mais sans « + » ni « 00» (ex: « 33612345678 »,
 * « 393336487419 »). On reconnaît un indicatif au début du numéro seulement
 * s'il figure dans INDICATIFS_CONNUS ET que la longueur du reste correspond à
 * un numéro national plausible pour ce pays — sans ces deux conditions,
 * impossible de deviner le pays sans risque de se tromper (un numéro local
 * français en 0612345678 ressemble à trop d'autres formats nationaux). Dans
 * ce cas, normaliser() renvoie le numéro nettoyé mais sans indicatif : c'est
 * à l'appelant de refuser cette valeur ambiguë via estAmbigu() et de demander
 * explicitement l'indicatif à l'utilisateur plutôt que de l'accepter en silence.
 */
class Telephone
{
    protected const PREFIXES_MOBILES_SENEGAL = ['70', '75', '76', '77', '78'];

    /**
     * Indicatifs observés dans les fichiers réels de la plateforme, avec la ou les
     * longueurs de numéro national qu'ils autorisent — ce qui permet de les
     * reconnaître même collés sans « + » ni « 00 ». Vérifiés des plus longs
     * (3 chiffres) aux plus courts (1 chiffre) pour ne jamais couper un indicatif
     * plus précis au milieu (« 221... » ne doit pas être lu comme « 22 » + reste).
     *
     * Sénégal (221) et Mauritanie (222) sont volontairement absents d'ici : ils
     * sont gérés par les règles dédiées ci-dessous (Sénégal a aussi un format
     * local sans aucun indicatif, le plus courant sur la plateforme).
     */
    protected const INDICATIFS_CONNUS = [
        // 3 chiffres
        '212' => [9],       // Maroc
        '966' => [9],       // Arabie saoudite
        '971' => [9],       // Émirats arabes unis
        // 2 chiffres
        '33' => [9],        // France
        '34' => [9],        // Espagne
        '39' => [9, 10],    // Italie
        '44' => [10],       // Royaume-Uni
        '45' => [8],        // Danemark
        '49' => [10, 11],   // Allemagne
        '60' => [9, 10],    // Malaisie
        // 1 chiffre
        '1' => [10],        // États-Unis / Canada (NANP)
    ];

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

        // Numero local senegalais (9 chiffres, prefixe mobile reconnu, sans aucun
        // indicatif) : le cas le plus frequent sur la plateforme.
        if (strlen($chiffres) === 9 && in_array(substr($chiffres, 0, 2), static::PREFIXES_MOBILES_SENEGAL, true)) {
            return '+221' . $chiffres;
        }

        // Senegal (221) et Mauritanie (222) colles sans "+" ni "00" (ex: 221775491060,
        // 22233623564) : Mauritanie (222, national a 8 chiffres) est verifie avant
        // Senegal (221, national a 9 chiffres) pour ne pas confondre un "222..." avec
        // un "221..." suivi d'un chiffre en trop.
        if (str_starts_with($chiffres, '222') && strlen($chiffres) === 11) {
            return '+222' . substr($chiffres, 3);
        }
        if (str_starts_with($chiffres, '221') && strlen($chiffres) === 12) {
            return '+221' . substr($chiffres, 3);
        }

        // Autres indicatifs connus colles sans "+" ni "00" (ex: 33612345678,
        // 393336487419, 447954441234) : verifies du plus long au plus court.
        foreach ([3, 2, 1] as $longueurIndicatif) {
            $indicatif = substr($chiffres, 0, $longueurIndicatif);
            $longueursNationalAttendues = static::INDICATIFS_CONNUS[$indicatif] ?? null;

            if ($longueursNationalAttendues === null) {
                continue;
            }

            if (in_array(strlen($chiffres) - $longueurIndicatif, $longueursNationalAttendues, true)) {
                return '+' . $chiffres;
            }
        }

        // Numero etranger sans indicatif reconnaissable (ex: 0612345678) : le pays
        // n'est pas devine, mais la forme est stable pour les comparaisons futures —
        // au moins les variantes d'espacement/ponctuation du meme numero se retrouvent.
        return $chiffres;
    }

    /** True si les deux valeurs, une fois normalisees, designent le meme numero. */
    public static function identiques(?string $a, ?string $b): bool
    {
        $a = static::normaliser($a);
        $b = static::normaliser($b);

        return $a !== null && $a === $b;
    }

    /**
     * True si la valeur ressemble a un numero etranger dont l'indicatif n'a pas pu
     * etre reconnu (normaliser() renvoie alors des chiffres bruts, sans "+") — plutot
     * que d'accepter ce numero ambigu en silence, l'appelant doit demander
     * explicitement l'indicatif pays a l'utilisateur (ex: "+33 pour la France").
     */
    /**
     * La case « même numéro sur WhatsApp » doit-elle être cochée ?
     *
     * Oui quand les deux numéros se valent, mais aussi quand il n'y en a aucun :
     * sur un dossier vide, il n'y a rien à distinguer, et revenir décoché
     * suggérerait à tort que l'investisseur a deux numéros différents.
     *
     * Volontairement distinct d'identiques(), qui sert à détecter les doublons :
     * là, deux numéros absents ne sont pas « la même personne ».
     */
    public static function memeNumero(?string $telephone, ?string $whatsapp): bool
    {
        if (blank($telephone) && blank($whatsapp)) {
            return true;
        }

        return self::identiques($telephone, $whatsapp);
    }

    /**
     * Numéro WhatsApp à enregistrer.
     *
     * Le cas courant est « le même que le téléphone » : la case est cochée et il
     * n'y a rien à saisir. On recopie alors le téléphone plutôt que de laisser la
     * colonne vide, qui se lirait comme « pas de WhatsApp » au lieu de « même
     * numéro ». Décochée, c'est le numéro saisi qui compte — normalisé comme
     * tous les autres, pour que la comparaison reste possible.
     */
    public static function pourWhatsapp(bool $memeNumero, ?string $telephone, ?string $saisi): ?string
    {
        return self::normaliser($memeNumero ? $telephone : $saisi);
    }

    public static function estAmbigu(?string $valeur): bool
    {
        $normalise = static::normaliser($valeur);

        return $normalise !== null && ! str_starts_with($normalise, '+');
    }
}
