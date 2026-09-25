<?php

namespace App\Support\Piece;

use Carbon\Carbon;

/**
 * Numéro de la carte d'identité biométrique CEDEAO du Sénégal.
 *
 * Le décret d'application de la loi 2016-09 en fixe la composition : 17 chiffres,
 * dont la date de naissance du titulaire en clair.
 *
 *     1 chiffre   sexe (1 masculin, 2 féminin)
 *     2 chiffres  région d'enregistrement
 *     8 chiffres  date de naissance, AAAAMMJJ
 *     5 chiffres  numéro généré
 *     1 chiffre   clé de contrôle
 *
 * Cette composition change la nature de ce qu'on lit. Le numéro est imprimé au
 * recto, hors de la bande : rien ne devrait permettre de vérifier sa lecture. Mais
 * comme il contient une date, une lecture fautive produit presque toujours une
 * date impossible — et quand la bande est lisible, les deux dates doivent
 * coïncider. On dispose donc d'un contrôle là où il n'y en avait pas.
 *
 * L'algorithme de la clé de contrôle n'est pas publié : elle n'est pas vérifiée.
 * On se contente de ce qui est vérifiable, et on le dit.
 */
class NumeroCni
{
    /** Le Sénégal compte quatorze régions administratives. */
    private const REGION_MAX = 14;

    /** Personne née avant cette année : lecture jugée fautive plutôt que centenaire. */
    private const AGE_MAX = 120;

    /**
     * Le numéro a-t-il la forme prévue par le décret ?
     *
     * Ne dit rien de la clé de contrôle, dont l'algorithme n'est pas public.
     */
    public static function structureValide(string $numero): bool
    {
        return self::dateDeNaissance($numero) !== null;
    }

    /**
     * Date de naissance portée par le numéro, ou null si elle n'est pas plausible.
     *
     * C'est la partie qui rend la lecture vérifiable : un chiffre mal reconnu
     * dans ces huit positions donne presque toujours un mois à 19 ou un jour à 47.
     */
    public static function dateDeNaissance(string $numero): ?string
    {
        $chiffres = preg_replace('/\D/', '', $numero) ?? '';

        if (strlen($chiffres) !== 17) {
            return null;
        }

        if (! in_array($chiffres[0], ['1', '2'], true)) {
            return null;
        }

        $region = (int) substr($chiffres, 1, 2);

        if ($region < 1 || $region > self::REGION_MAX) {
            return null;
        }

        $annee = (int) substr($chiffres, 3, 4);
        $mois = (int) substr($chiffres, 7, 2);
        $jour = (int) substr($chiffres, 9, 2);

        if (! checkdate($mois, $jour, $annee)) {
            return null;
        }

        $date = Carbon::createFromDate($annee, $mois, $jour);

        if ($date->isFuture() || $date->year < now()->year - self::AGE_MAX) {
            return null;
        }

        return $date->toDateString();
    }

    /**
     * Le numéro et la bande racontent-ils la même naissance ?
     *
     * Deux lectures indépendantes — le recto au jugé, la bande sous clé de
     * contrôle — qui tombent sur la même date : c'est la meilleure confirmation
     * disponible sur cette carte.
     */
    public static function concordeAvec(string $numero, ?string $dateDeLaBande): bool
    {
        $date = self::dateDeNaissance($numero);

        return $date !== null && $dateDeLaBande !== null && $date === $dateDeLaBande;
    }
}
