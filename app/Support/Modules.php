<?php

namespace App\Support;

/**
 * Les modules sur lesquels un droit se donne, et les rôles à qui le donner.
 *
 * Jusqu'ici les droits étaient écrits en dur à trois endroits — les groupes de
 * routes, la barre latérale, et des contrôles dispersés dans les composants — ce
 * qui obligeait à modifier le code pour changer qui voit quoi. Ce catalogue est
 * désormais la seule liste qui fasse foi ; la table `permissions` dit, pour
 * chaque couple rôle/module, jusqu'où l'on peut aller.
 *
 * Découper plus finement serait tentant mais se paierait à l'usage : un écran de
 * paramétrage qui propose quarante interrupteurs n'est plus réglé par personne.
 * Le grain retenu est celui de la barre latérale, celui que l'exploitant a déjà
 * en tête.
 */
class Modules
{
    public const AUCUN = 'aucun';

    public const LECTURE = 'lecture';

    public const ECRITURE = 'ecriture';

    /**
     * Les rôles d'exploitation, dans l'ordre décroissant d'étendue.
     *
     * `investisseur` n'y figure pas : il n'accède qu'à son propre portail, par
     * des routes qui ne passent pas par ce contrôle. Lui donner une colonne dans
     * la grille laisserait croire qu'on peut lui ouvrir la gestion.
     */
    public const ROLES = ['direction', 'administrateur', 'gestionnaire', 'lecture'];

    /**
     * code => [libellé, ce que le module couvre, niveau maximal atteignable]
     *
     * Le niveau maximal n'est pas une préférence mais une propriété du module :
     * un journal d'audit ne s'écrit pas à la main, des exports ne se modifient
     * pas. Proposer « écriture » sur ces lignes offrirait un réglage sans effet.
     */
    public const CATALOGUE = [
        'investisseurs' => [
            'libelle' => 'Investisseurs',
            'portee' => 'Dossiers, comptes, achats, radiations, dons, relevés',
            'maximum' => self::ECRITURE,
        ],
        'dividendes' => [
            'libelle' => 'Dividendes',
            'portee' => 'Barèmes mensuels, distribution, correction rétroactive',
            'maximum' => self::ECRITURE,
        ],
        'successions' => [
            'libelle' => 'Successions',
            'portee' => 'Déclaration de décès, héritiers, liquidation, versement',
            'maximum' => self::ECRITURE,
        ],
        'gestionnaires' => [
            'libelle' => 'Gestionnaires',
            'portee' => 'Création, désactivation, réassignation de portefeuille',
            'maximum' => self::ECRITURE,
        ],
        'import' => [
            'libelle' => 'Import',
            'portee' => 'Reprise de données depuis un fichier Excel ou CSV',
            'maximum' => self::ECRITURE,
        ],
        'exports' => [
            'libelle' => 'Exports',
            'portee' => 'Téléchargement des listes en CSV et en PDF',
            'maximum' => self::LECTURE,
        ],
        'audit' => [
            'libelle' => "Journal d'audit",
            'portee' => 'Historique des actions sensibles, et son export',
            'maximum' => self::LECTURE,
        ],
        'parametrage' => [
            'libelle' => 'Paramétrage',
            'portee' => 'Droits par rôle, comptes utilisateurs, champs du dossier',
            'maximum' => self::ECRITURE,
        ],
    ];

    /**
     * Le module sans lequel on ne peut plus rien reprendre en main.
     *
     * L'administrateur le garde toujours en écriture : une grille où personne
     * n'a plus accès au paramétrage se verrouille de l'extérieur, et il faudrait
     * repasser par la base pour en sortir.
     */
    public const MODULE_VERROU = 'parametrage';

    public const ROLE_VERROU = 'administrateur';

    /**
     * La grille de départ, qui reproduit exactement les droits en vigueur avant
     * que cet écran n'existe. Le paramétrage commence donc sans rien changer :
     * ce qui marchait la veille marche le lendemain.
     *
     * @return array<string, array<string, string>> role => module => niveau
     */
    public static function grilleInitiale(): array
    {
        $aucun = array_fill_keys(array_keys(self::CATALOGUE), self::AUCUN);

        $administration = array_merge($aucun, [
            'investisseurs' => self::ECRITURE,
            'dividendes' => self::ECRITURE,
            'successions' => self::ECRITURE,
            'gestionnaires' => self::ECRITURE,
            'import' => self::ECRITURE,
            'exports' => self::LECTURE,
            'audit' => self::LECTURE,
            'parametrage' => self::ECRITURE,
        ]);

        return [
            'direction' => $administration,
            'administrateur' => $administration,

            // Le gestionnaire travaille les dossiers de son portefeuille et rien
            // d'autre — le cloisonnement par portefeuille reste porté ailleurs.
            'gestionnaire' => array_merge($aucun, [
                'investisseurs' => self::ECRITURE,
                'exports' => self::LECTURE,
            ]),

            // Le rôle Lecture regarde sans jamais écrire.
            'lecture' => array_merge($aucun, [
                'investisseurs' => self::LECTURE,
                'exports' => self::LECTURE,
            ]),
        ];
    }

    /** Les niveaux proposables pour ce module, du plus faible au plus fort. */
    public static function niveauxPossibles(string $module): array
    {
        $maximum = self::CATALOGUE[$module]['maximum'] ?? self::ECRITURE;

        return $maximum === self::LECTURE
            ? [self::AUCUN, self::LECTURE]
            : [self::AUCUN, self::LECTURE, self::ECRITURE];
    }

    public static function existe(string $module): bool
    {
        return array_key_exists($module, self::CATALOGUE);
    }

    public static function libelle(string $module): string
    {
        return self::CATALOGUE[$module]['libelle'] ?? $module;
    }

    /** Écriture couvre lecture : qui peut modifier peut forcément consulter. */
    public static function couvre(string $accorde, string $exige): bool
    {
        $rang = [self::AUCUN => 0, self::LECTURE => 1, self::ECRITURE => 2];

        return ($rang[$accorde] ?? 0) >= ($rang[$exige] ?? 0);
    }
}
