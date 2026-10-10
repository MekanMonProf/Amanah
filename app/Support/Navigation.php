<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * Arborescence de la barre latérale.
 *
 * Chaque entrée nomme le module qu'elle ouvre, et c'est la table des permissions
 * qui décide si elle s'affiche — la même table que le middleware des routes.
 * Auparavant deux listes de rôles devaient rester alignées à la main, l'une ici,
 * l'autre dans routes/web.php ; elles n'avaient aucun moyen de se contredire
 * bruyamment, seulement celui de dériver en silence.
 */
class Navigation
{
    /**
     * En dessous de ce nombre d'entrées visibles, les intitulés de groupe
     * coûtent plus de place qu'ils n'apportent de repères : la liste est
     * alors rendue à plat. Un gestionnaire n'a que trois entrées.
     */
    private const SEUIL_GROUPES = 4;

    /**
     * Groupes visibles par cet utilisateur, chacun sous la forme
     * ['libelle' => ?string, 'entrees' => array<array{libelle,route,motif,icone}>].
     */
    public static function groupes(User $utilisateur): array
    {
        $groupes = self::filtrer(self::arborescence(), $utilisateur);

        $total = array_sum(array_map(fn ($g) => count($g['entrees']), $groupes));

        if ($total < self::SEUIL_GROUPES) {
            return [[
                'libelle' => null,
                'entrees' => array_merge(...array_column($groupes, 'entrees')),
            ]];
        }

        return $groupes;
    }

    /**
     * Au-delà de ce nombre, la barre d'onglets garde les premières entrées et
     * renvoie le reste au tiroir. Cinq cases est ce qu'un pouce vise sans effort
     * sur un téléphone ; au-delà les libellés se coupent et plus rien ne se lit.
     */
    private const ONGLETS_MAX = 5;

    /**
     * Les entrées de la barre d'onglets du bas, sur écran étroit.
     *
     * Elle ne remplace pas le tiroir, elle met à portée ce qu'on ouvre dix fois
     * par jour. Quand tout ne tient pas, la dernière case devient « Plus » et
     * ouvre le tiroir, où le menu complet reste disponible.
     *
     * @return array{entrees: array<int, array>, davantage: bool}
     */
    public static function onglets(User $utilisateur): array
    {
        $toutes = [];

        foreach (self::groupes($utilisateur) as $groupe) {
            foreach ($groupe['entrees'] as $entree) {
                $toutes[] = $entree;
            }
        }

        // Une seule destination ne fait pas une barre de navigation : l'investisseur
        // qui n'a que son compte n'a nulle part où aller.
        if (count($toutes) < 2) {
            return ['entrees' => [], 'davantage' => false];
        }

        if (count($toutes) <= self::ONGLETS_MAX) {
            return ['entrees' => $toutes, 'davantage' => false];
        }

        return [
            'entrees' => array_slice($toutes, 0, self::ONGLETS_MAX - 1),
            'davantage' => true,
        ];
    }

    /**
     * Libellé de l'entrée correspondant à la page courante, pour l'afficher
     * dans la barre supérieure. Null si aucune entrée ne correspond.
     */
    public static function sectionCourante(User $utilisateur): ?string
    {
        foreach (self::groupes($utilisateur) as $groupe) {
            foreach ($groupe['entrees'] as $entree) {
                if (self::estActive($entree)) {
                    return $entree['libelle'];
                }
            }
        }

        return null;
    }

    /** Une entrée est active si la route courante correspond à l'un de ses motifs. */
    public static function estActive(array $entree): bool
    {
        return Route::currentRouteName() !== null
            && request()->routeIs(...$entree['motifs']);
    }

    private static function arborescence(): array
    {
        return [
            [
                'libelle' => null,
                'entrees' => [
                    // Le portail n'est pas un module : son accès ne se paramètre
                    // pas, il découle du rôle. D'où le module null, toujours visible
                    // pour l'investisseur et pour lui seul.
                    self::entree('Mon compte', 'portail.mon-compte', ['portail.mon-compte'], 'compte', null, 'investisseur'),
                    // Ses motifs étaient 'portail.*', ce qui gardait « Mon compte »
                    // allumé en lisant ses relevés. Chacun désigne désormais sa page.
                    self::entree('Mes relevés', 'portail.releves.index', ['portail.releves.*'], 'releves', null, 'investisseur', 'Relevés'),
                    self::entree('Mes dividendes', 'portail.dividendes.index', ['portail.dividendes.*'], 'dividendes', null, 'investisseur', 'Dividendes'),
                    self::entree('Mes documents', 'portail.documents.index', ['portail.documents.*'], 'documents', null, 'investisseur', 'Documents'),
                    self::entree('Tableau de bord', 'dashboard', ['dashboard'], 'tableau', null, null, 'Tableau'),
                ],
            ],
            [
                'libelle' => 'Gestion',
                'entrees' => [
                    self::entree('Investisseurs', 'investisseurs.index', ['investisseurs.*', 'achats.*', 'comptes.*', 'radiations.*', 'dons.*'], 'investisseurs', 'investisseurs', null, 'Dossiers'),
                    self::entree('Gestionnaires', 'gestionnaires.index', ['gestionnaires.*'], 'gestionnaires', 'gestionnaires', null, 'Gestion.'),
                ],
            ],
            [
                'libelle' => 'Finance',
                'entrees' => [
                    self::entree('Dividendes', 'dividendes.calculer', ['dividendes.*', 'baremes.*'], 'dividendes', 'dividendes'),
                    self::entree('Successions', 'successions.index', ['successions.*', 'deces.*'], 'successions', 'successions'),
                ],
            ],
            [
                'libelle' => 'Administration',
                'entrees' => [
                    self::entree('Exports', 'exports.index', ['exports.index'], 'exports', 'exports'),
                    self::entree('Import', 'import.index', ['import.*'], 'import', 'import'),
                    self::entree("Journal d'audit", 'audit.index', ['audit.*'], 'audit', 'audit', null, 'Journal'),
                    self::entree('Paramétrage', 'parametrage.index', ['parametrage.*'], 'parametrage', 'parametrage', null, 'Réglages'),
                ],
            ],
            [
                'libelle' => 'Aide',
                'entrees' => [
                    // Ouvertes a tout le monde, investisseur compris : un mode
                    // d'emploi reserve a ceux qui savent deja s'en passer ne
                    // sert personne. Le detail de ce qu'on y lit se filtre page
                    // par page, dans App\Support\Aide.
                    // Les documents de la direction s'adressent à tous : au
                    // personnel comme aux investisseurs. D'où « pour tous »,
                    // comme l'aide — ce n'est pas un module qui se paramètre.
                    self::entree('Documents officiels', 'documents-officiels.index', ['documents-officiels.*'], 'documents', null, null, 'Officiels', true),
                    self::entree("Mode d'emploi", 'aide.index', ['aide.*'], 'aide', null, null, 'Aide', true),
                    self::entree('Contacter le support', 'support.contacter', ['support.*'], 'support', null, null, 'Support', true),
                ],
            ],
        ];
    }

    /**
     * @param  string|null  $module     Module dont l'accès conditionne l'entrée.
     * @param  string|null  $roleReserve Rôle exclusif, pour ce qui ne relève d'aucun module.
     */
    /**
     * @param  string|null  $module      Module dont l'accès conditionne l'entrée.
     * @param  string|null  $roleReserve Rôle exclusif, pour ce qui ne relève d'aucun module.
     * @param  string|null  $libelleCourt Version brève pour la barre d'onglets, où la
     *                                    case fait un cinquième de l'écran : un
     *                                    intitulé coupé en son milieu ne se lit pas.
     */
    private static function entree(string $libelle, string $route, array $motifs, string $icone, ?string $module, ?string $roleReserve = null, ?string $libelleCourt = null, bool $pourTous = false): array
    {
        return compact('libelle', 'route', 'motifs', 'icone', 'module', 'roleReserve', 'libelleCourt', 'pourTous');
    }

    /** Retire les entrées inaccessibles, puis les groupes devenus vides. */
    private static function filtrer(array $groupes, User $utilisateur): array
    {
        $retenus = [];

        foreach ($groupes as $groupe) {
            $entrees = array_values(array_filter(
                $groupe['entrees'],
                fn ($entree) => self::estVisible($entree, $utilisateur),
            ));

            if ($entrees !== []) {
                $retenus[] = ['libelle' => $groupe['libelle'], 'entrees' => $entrees];
            }
        }

        return $retenus;
    }

    /**
     * Une entrée marquée « pour tous » s'affiche pour quiconque est connecté ;
     * une entrée réservée à un rôle s'affiche pour lui seul ; une entrée qui
     * porte un module s'affiche dès que ce module est lisible ; le tableau de
     * bord, qui n'est rien de tout cela, s'affiche pour tout le personnel.
     */
    private static function estVisible(array $entree, User $utilisateur): bool
    {
        // Le quatrieme cas : ni module ni role, visible par quiconque est
        // connecte. Sans lui, l'aide aurait suivi la regle du tableau de bord
        // et aurait disparu pour l'investisseur, qui en a le plus besoin.
        if ($entree['pourTous'] ?? false) {
            return true;
        }

        if ($entree['roleReserve'] !== null) {
            return $utilisateur->role === $entree['roleReserve'];
        }

        if ($entree['module'] !== null) {
            return Droits::peutLire($entree['module'], $utilisateur);
        }

        return in_array($utilisateur->role, Modules::ROLES, true);
    }
}
