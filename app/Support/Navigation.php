<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * Arborescence de la barre latérale.
 *
 * Les rôles listés ici ne servent qu'à masquer les entrées inutiles : la vraie
 * autorisation reste le middleware `role:` posé dans routes/web.php. Les deux
 * listes doivent rester alignées — d'où les constantes reprises telles quelles.
 */
class Navigation
{
    /** Niveau 1 de routes/web.php — consultation. */
    public const CONSULTATION = ['direction', 'administrateur', 'gestionnaire', 'lecture'];

    /** Niveau 3 de routes/web.php — administration. */
    public const ADMINISTRATION = ['direction', 'administrateur'];

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
        $groupes = self::filtrer(self::arborescence(), $utilisateur->role);

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
                    self::entree('Mon compte', 'portail.mon-compte', ['portail.*'], 'compte', ['investisseur']),
                    self::entree('Tableau de bord', 'dashboard', ['dashboard'], 'tableau', self::CONSULTATION),
                ],
            ],
            [
                'libelle' => 'Gestion',
                'entrees' => [
                    self::entree('Investisseurs', 'investisseurs.index', ['investisseurs.*', 'achats.*', 'comptes.*', 'radiations.*', 'dons.*'], 'investisseurs', self::CONSULTATION),
                    self::entree('Gestionnaires', 'gestionnaires.index', ['gestionnaires.*'], 'gestionnaires', self::ADMINISTRATION),
                ],
            ],
            [
                'libelle' => 'Finance',
                'entrees' => [
                    self::entree('Dividendes', 'dividendes.calculer', ['dividendes.*', 'baremes.*'], 'dividendes', self::ADMINISTRATION),
                    self::entree('Successions', 'successions.index', ['successions.*', 'deces.*'], 'successions', self::ADMINISTRATION),
                ],
            ],
            [
                'libelle' => 'Administration',
                'entrees' => [
                    self::entree('Exports', 'exports.index', ['exports.index'], 'exports', self::CONSULTATION),
                    self::entree('Import', 'import.index', ['import.*'], 'import', self::ADMINISTRATION),
                    self::entree("Journal d'audit", 'audit.index', ['audit.*'], 'audit', self::ADMINISTRATION),
                ],
            ],
        ];
    }

    private static function entree(string $libelle, string $route, array $motifs, string $icone, array $roles): array
    {
        return compact('libelle', 'route', 'motifs', 'icone', 'roles');
    }

    /** Retire les entrées interdites à ce rôle, puis les groupes devenus vides. */
    private static function filtrer(array $groupes, string $role): array
    {
        $retenus = [];

        foreach ($groupes as $groupe) {
            $entrees = array_values(array_filter(
                $groupe['entrees'],
                fn ($entree) => in_array($role, $entree['roles'], true),
            ));

            if ($entrees !== []) {
                $retenus[] = ['libelle' => $groupe['libelle'], 'entrees' => $entrees];
            }
        }

        return $retenus;
    }
}
