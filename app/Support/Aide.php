<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\View;

/**
 * Le sommaire du mode d'emploi.
 *
 * Les textes eux-mêmes ne sont pas ici : chaque sujet est une vue sous
 * `resources/views/aide/{langue}/{code}.blade.php`. Les faire passer par les
 * fichiers de traduction aurait découpé des pages entières en centaines de
 * clés, impossibles à relire et à corriger — une page d'aide se relit d'un
 * bloc, phrase après phrase, ou elle ne se relit pas.
 *
 * Une langue sans fichier retombe sur le français, et la page le dit. C'est
 * plus honnête qu'une traduction automatique : le lecteur sait qu'il lit la
 * version d'origine, et le traducteur voit ce qui reste à faire.
 */
class Aide
{
    /** La langue dans laquelle toutes les pages existent, et vers laquelle on retombe. */
    public const LANGUE_SOURCE = 'fr';

    public const PUBLIC_TOUS = 'tous';

    public const PUBLIC_EQUIPE = 'equipe';

    public const PUBLIC_ADMINISTRATION = 'administration';

    /**
     * code => [titre, resume, icone, public]
     *
     * L'ordre est celui de la lecture, pas de l'alphabet : on commence par
     * s'orienter, puis on suit la vie d'un dossier — il s'ouvre, il achète, il
     * touche, il sort — avant les sujets qui ne concernent qu'une partie des
     * utilisateurs.
     */
    public const SUJETS = [
        'demarrer' => [
            'titre' => 'Premiers pas',
            'resume' => "Se connecter, se repérer dans l'application, changer de langue.",
            'icone' => 'tableau',
            'public' => self::PUBLIC_TOUS,
        ],
        'portail' => [
            'titre' => 'Mon espace investisseur',
            'resume' => "Lire sa position, ses mouvements, et télécharger son relevé.",
            'icone' => 'compte',
            'public' => self::PUBLIC_TOUS,
        ],
        'dossiers' => [
            'titre' => 'Les dossiers investisseurs',
            'resume' => "Ouvrir un dossier, le compléter, donner l'accès au portail.",
            'icone' => 'investisseurs',
            'public' => self::PUBLIC_EQUIPE,
        ],
        'achats' => [
            'titre' => "Achats, compléments et dons",
            'resume' => "Enregistrer une souscription, alimenter un solde, offrir des actions.",
            'icone' => 'investisseurs',
            'public' => self::PUBLIC_EQUIPE,
        ],
        'dividendes' => [
            'titre' => 'Les dividendes',
            'resume' => "Fixer le barème d'un mois, distribuer, corriger une erreur de taux.",
            'icone' => 'dividendes',
            'public' => self::PUBLIC_EQUIPE,
        ],
        'radiations' => [
            'titre' => 'Radiations et versements',
            'resume' => "Sortir un investisseur, lui verser son capital, attester le paiement.",
            'icone' => 'investisseurs',
            'public' => self::PUBLIC_EQUIPE,
        ],
        'successions' => [
            'titre' => 'Décès et successions',
            'resume' => "Déclarer un décès, désigner les héritiers, répartir et verser.",
            'icone' => 'successions',
            'public' => self::PUBLIC_EQUIPE,
        ],
        'import' => [
            'titre' => 'Importer des données',
            'resume' => "Reprendre un fichier Excel ou CSV sans rien écraser par surprise.",
            'icone' => 'import',
            'public' => self::PUBLIC_EQUIPE,
        ],
        'exports' => [
            'titre' => 'Exports et relevés',
            'resume' => "Sortir une liste en CSV, un relevé ou une attestation en PDF.",
            'icone' => 'exports',
            'public' => self::PUBLIC_EQUIPE,
        ],
        'parametrage' => [
            'titre' => 'Paramétrage',
            'resume' => "Droits par rôle, comptes, champs du dossier, règles de dividende.",
            'icone' => 'parametrage',
            'public' => self::PUBLIC_ADMINISTRATION,
        ],
    ];

    /** Le sujet existe-t-il au catalogue ? */
    public static function existe(string $code): bool
    {
        return array_key_exists($code, self::SUJETS);
    }

    /**
     * Les sujets que cette personne a intérêt à lire.
     *
     * Un investisseur n'a que faire de la page des successions : il ne verra
     * jamais l'écran qu'elle décrit. Le filtre suit le rôle et non les droits
     * fins — lire comment marche un écran qu'on ne peut qu'ouvrir en lecture
     * n'a jamais nui à personne.
     *
     * @return array<string, array> code => définition
     */
    public static function pour(?User $utilisateur): array
    {
        $role = $utilisateur?->role;

        return array_filter(
            self::SUJETS,
            fn (array $sujet) => match ($sujet['public']) {
                self::PUBLIC_TOUS => true,
                self::PUBLIC_EQUIPE => in_array($role, Modules::ROLES, true),
                self::PUBLIC_ADMINISTRATION => in_array($role, ['direction', 'administrateur'], true),
                default => false,
            },
        );
    }

    public static function accessible(string $code, ?User $utilisateur): bool
    {
        return array_key_exists($code, self::pour($utilisateur));
    }

    /**
     * La vue à rendre pour ce sujet, dans cette langue si elle existe.
     *
     * @return array{vue: string, langue: string, traduite: bool}
     */
    public static function page(string $code, ?string $langue = null): array
    {
        $langue ??= app()->getLocale();
        $vue = "aide.{$langue}.{$code}";

        if ($langue !== self::LANGUE_SOURCE && View::exists($vue)) {
            return ['vue' => $vue, 'langue' => $langue, 'traduite' => true];
        }

        return [
            'vue' => 'aide.' . self::LANGUE_SOURCE . ".{$code}",
            'langue' => self::LANGUE_SOURCE,
            'traduite' => $langue === self::LANGUE_SOURCE,
        ];
    }

    public static function titre(string $code): string
    {
        return self::SUJETS[$code]['titre'] ?? $code;
    }

    /** Le sujet suivant et le précédent, pour lire le manuel de bout en bout. */
    public static function voisins(string $code, ?User $utilisateur): array
    {
        $codes = array_keys(self::pour($utilisateur));
        $place = array_search($code, $codes, true);

        if ($place === false) {
            return ['precedent' => null, 'suivant' => null];
        }

        return [
            'precedent' => $codes[$place - 1] ?? null,
            'suivant' => $codes[$place + 1] ?? null,
        ];
    }
}
