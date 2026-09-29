<?php

namespace App\Support;

use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Où vivent les tables de l'application qui existait avant AMANAH.
 *
 * Deux dispositions, selon l'hébergement. Le forfait de production n'autorise
 * qu'une seule base : AMANAH y pose ses tables à côté des anciennes, et il n'y
 * a rien à configurer. Ailleurs — un poste de développement, un hébergement
 * plus large — l'ancienne application peut vivre dans sa propre base, et
 * ANCIENNE_DB_DATABASE dit alors où chercher.
 *
 * On ne devine jamais : la présence des tables témoins est vérifiée avant de
 * lire quoi que ce soit. Inventorier ou reprendre la mauvaise base serait pire
 * que de ne rien faire.
 */
class AncienneBase
{
    /** Les tables qui signent l'ancienne application. */
    public const TEMOINS = ['utilisateurs', 'releves', 'antennes'];

    /** Les rôles de l'ancienne application, par leur code. */
    public const ROLE_ADMIN = 'ADMIN';
    public const ROLE_SECRETAIRE = 'S';
    public const ROLE_ACTIONNAIRE = 'A';

    /**
     * La connexion à utiliser, ou null si l'ancienne application est introuvable.
     *
     * @param  list<string>  $manquantes  Reçoit les tables témoins absentes.
     */
    public static function connexion(array &$manquantes = []): ?string
    {
        $connexion = self::connexionEssayee();

        try {
            $manquantes = array_values(array_filter(
                self::TEMOINS,
                fn ($table) => ! Schema::connection($connexion)->hasTable($table),
            ));
        } catch (Throwable) {
            $manquantes = self::TEMOINS;

            return null;
        }

        return $manquantes === [] ? $connexion : null;
    }

    /**
     * La connexion interrogee, qu'on y trouve l'ancienne application ou non.
     *
     * Sert au message d'echec : dire « base introuvable » sans nommer la base
     * ou l'on a cherche n'aide personne a corriger le tir.
     */
    public static function connexionEssayee(): string
    {
        return self::estSeparee() ? 'ancienne' : (string) config('database.default');
    }

    public static function estSeparee(): bool
    {
        return filled(config('database.connections.ancienne.database'));
    }

    public static function base(string $connexion): string
    {
        return (string) config("database.connections.{$connexion}.database");
    }
}
