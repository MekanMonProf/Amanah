<?php

namespace App\Support;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * La question « cette personne peut-elle faire cela ? », posée à un seul endroit.
 *
 * Tout le reste de l'application passe par ici : le middleware des routes, la
 * barre latérale, et les contrôles à l'intérieur des composants. Une règle qui
 * change se change donc une fois.
 *
 * La grille est relue une fois par requête et gardée en mémoire : trente-deux
 * lignes ne justifient pas un cache à invalider, mais les relire à chaque
 * bouton de la barre latérale ferait une dizaine de requêtes par page.
 */
class Droits
{
    /** @var array<string, array<string, string>>|null role => module => niveau */
    private static ?array $grille = null;

    public static function niveau(?User $utilisateur, string $module): string
    {
        if (! $utilisateur) {
            return Modules::AUCUN;
        }

        // L'administrateur garde la clé du paramétrage quoi qu'il arrive : une
        // grille mal réglée doit rester rattrapable depuis l'application.
        if ($module === Modules::MODULE_VERROU && $utilisateur->role === Modules::ROLE_VERROU) {
            return Modules::ECRITURE;
        }

        return self::grille()[$utilisateur->role][$module] ?? Modules::AUCUN;
    }

    /** L'utilisateur connecté atteint-il ce niveau sur ce module ? */
    public static function autorise(string $module, string $niveauExige = Modules::LECTURE, ?User $utilisateur = null): bool
    {
        $utilisateur ??= Auth::user();

        return Modules::couvre(self::niveau($utilisateur, $module), $niveauExige);
    }

    public static function peutLire(string $module, ?User $utilisateur = null): bool
    {
        return self::autorise($module, Modules::LECTURE, $utilisateur);
    }

    public static function peutEcrire(string $module, ?User $utilisateur = null): bool
    {
        return self::autorise($module, Modules::ECRITURE, $utilisateur);
    }

    /**
     * Refuse l'action plutôt que de la laisser passer, avec un message qui dit
     * ce qui manque — « accès refusé » sans plus laisse l'exploitant deviner
     * quel réglage revoir.
     */
    public static function exiger(string $module, string $niveauExige = Modules::LECTURE): void
    {
        abort_unless(self::autorise($module, $niveauExige), 403, __(
            "Votre rôle ne donne pas l'accès « :niveau » au module :module.",
            ['niveau' => $niveauExige, 'module' => Modules::libelle($module)],
        ));
    }

    /** @return array<string, array<string, string>> */
    public static function grille(): array
    {
        if (self::$grille === null) {
            self::$grille = Permission::all()
                ->groupBy('role')
                ->map(fn ($lignes) => $lignes->pluck('niveau', 'module')->all())
                ->all();
        }

        return self::$grille;
    }

    /**
     * À appeler après toute écriture dans la table : sans cela, la même requête
     * continuerait de répondre avec la grille d'avant, et l'écran de paramétrage
     * afficherait l'ancien état juste après l'enregistrement.
     */
    public static function oublier(): void
    {
        self::$grille = null;
    }
}
