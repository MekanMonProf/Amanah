<?php

namespace App\Support;

/**
 * Génère les mots de passe temporaires remis à un investisseur ou à un gestionnaire.
 *
 * La plupart des investisseurs n'ayant pas d'email, le gestionnaire leur transmet ce
 * mot de passe de vive voix ou sur papier : il doit donc se dicter sans ambiguïté.
 * D'où le format retenu — majuscules uniquement (aucune casse à épeler), alphabet
 * débarrassé des caractères qu'on confond à l'oral comme à l'écrit, et découpage
 * par un tiret pour ne pas perdre sa place en lisant :
 *
 *   A0001-K7RMPX   (accès investisseur : son identifiant, qu'il connaît déjà, + un bloc aléatoire)
 *   K7RM-PX9W      (gestionnaire, qui n'a pas d'identifiant de ce type)
 *
 * Le préfixe n'apporte AUCUNE sécurité : l'identifiant d'un investisseur est visible
 * par tout le personnel (y compris un compte en lecture seule) et figure sur ses
 * attestations et relevés. Il ne sert que de repère pour la personne qui reçoit le
 * mot de passe. Toute la solidité repose sur le bloc aléatoire, d'où sa longueur.
 */
class MotDePasseTemporaire
{
    /**
     * Ni O/0, ni I/L/1, ni S/5, ni Z/2, ni B/8 : ce qui reste ne peut être confondu,
     * qu'on l'épelle au téléphone ou qu'on le recopie depuis une feuille.
     */
    protected const ALPHABET = 'ACDEFGHJKMNPQRTUVWXY34679';

    /** Bloc aléatoire d'un mot de passe préfixé : 25^6, hors de portée d'un essai à l'aveugle. */
    protected const LONGUEUR_ALEATOIRE = 6;

    /** Les mots de passe restent au-dessus du minimum exigé à la connexion. */
    protected const LONGUEUR_MINIMALE = 8;

    public static function generer(?string $prefixe = null): string
    {
        $prefixe = strtoupper(trim((string) $prefixe));

        // Sans préfixe (gestionnaire) : deux blocs aléatoires, même logique de lisibilité.
        if ($prefixe === '') {
            return static::bloc(4) . '-' . static::bloc(4);
        }

        $longueur = max(
            static::LONGUEUR_ALEATOIRE,
            static::LONGUEUR_MINIMALE - strlen($prefixe) - 1,
        );

        return $prefixe . '-' . static::bloc($longueur);
    }

    protected static function bloc(int $longueur): string
    {
        $dernierIndex = strlen(static::ALPHABET) - 1;
        $bloc = '';

        for ($i = 0; $i < $longueur; $i++) {
            $bloc .= static::ALPHABET[random_int(0, $dernierIndex)];
        }

        return $bloc;
    }
}
