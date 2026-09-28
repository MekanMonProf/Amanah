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
                    self::entree('Mon compte', 'portail.mon-compte', ['portail.*'], 'compte', null, 'investisseur'),
                    self::entree('Tableau de bord', 'dashboard', ['dashboard'], 'tableau', null),
                ],
            ],
            [
                'libelle' => 'Gestion',
                'entrees' => [
                    self::entree('Investisseurs', 'investisseurs.index', ['investisseurs.*', 'achats.*', 'comptes.*', 'radiations.*', 'dons.*'], 'investisseurs', 'investisseurs'),
                    self::entree('Gestionnaires', 'gestionnaires.index', ['gestionnaires.*'], 'gestionnaires', 'gestionnaires'),
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
                    self::entree("Journal d'audit", 'audit.index', ['audit.*'], 'audit', 'audit'),
                    self::entree('Paramétrage', 'parametrage.index', ['parametrage.*'], 'parametrage', 'parametrage'),
                ],
            ],
        ];
    }

    /**
     * @param  string|null  $module     Module dont l'accès conditionne l'entrée.
     * @param  string|null  $roleReserve Rôle exclusif, pour ce qui ne relève d'aucun module.
     */
    private static function entree(string $libelle, string $route, array $motifs, string $icone, ?string $module, ?string $roleReserve = null): array
    {
        return compact('libelle', 'route', 'motifs', 'icone', 'module', 'roleReserve');
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
     * Une entrée réservée à un rôle s'affiche pour lui seul ; une entrée qui
     * porte un module s'affiche dès que ce module est lisible ; le tableau de
     * bord, qui n'est ni l'un ni l'autre, s'affiche pour tout le personnel.
     */
    private static function estVisible(array $entree, User $utilisateur): bool
    {
        if ($entree['roleReserve'] !== null) {
            return $utilisateur->role === $entree['roleReserve'];
        }

        if ($entree['module'] !== null) {
            return Droits::peutLire($entree['module'], $utilisateur);
        }

        return in_array($utilisateur->role, Modules::ROLES, true);
    }
}
