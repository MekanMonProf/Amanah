<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Facades\Lang;

/**
 * Observations écrites par l'application, traduisibles à l'affichage.
 *
 * Le principe est celui du reste de l'application : la clé de traduction est la
 * phrase française. La nouveauté est qu'on range cette clé en base avec ses
 * paramètres, au lieu de n'y laisser que la phrase déjà composée — une phrase
 * composée ne se retraduit pas.
 *
 * La colonne `observations` continue de recevoir le français : c'est elle que
 * lisent les exports, les PDF et l'audit, et c'est la version qui fait foi.
 *
 * Ce qu'une personne a tapé n'a pas de clé et n'est jamais traduit : ces
 * observations sont sa parole, et elles peuvent être produites en justification.
 * C'est pourquoi un motif ou une note saisie reste un paramètre brut, recopié
 * tel quel dans la phrase traduite.
 *
 * Les variantes « avec référence » sont des clés à part plutôt qu'un suffixe
 * collé : une phrase entière se traduit, un bout de phrase non.
 */
class Observation
{
    // --- Achats d'actions ---
    public const REINVESTISSEMENT = 'Réinvestissement automatique';
    public const REINVESTISSEMENT_SUCCESSION = 'Réinvestissement automatique — hérite de :defunt (succession)';
    public const ACHAT_SUR_SOLDE = 'Achat manuel depuis le solde disponible';
    public const ACHAT_COMPLEMENT = 'Achat suite à complément financier';

    // --- Mouvements du compte financier ---
    public const COMPLEMENT = 'Complément financier — :mode';
    public const COMPLEMENT_REF = 'Complément financier — :mode (réf. :reference)';
    public const VERSEMENT_INVESTISSEUR = "Versement à l'investisseur — :mode";
    public const VERSEMENT_INVESTISSEUR_REF = "Versement à l'investisseur — :mode (réf. :reference)";
    public const VERSEMENT_CAPITAL_RADIE = 'Versement du capital radié — :mode';
    public const VERSEMENT_CAPITAL_RADIE_REF = 'Versement du capital radié — :mode (réf. :reference)';
    public const DIVIDENDE = 'Dividende :periode';
    // L espace fine insecable avant CFA est celle de Montant : en arabe, une espace
    // ordinaire serait reordonnee et le montant s afficherait a l envers.
    public const CORRECTION_BAREME = 'Correction barème :periode (:categorie) : :ancien → :nouveau'
        . Montant::SEPARATEUR . 'CFA/action. Motif : :motif';
    public const RADIATION_CAPITAL = 'Radiation :numero — :actions action(s), capital disponible pour versement';

    // --- Dons ---
    public const DON_SORTANT = 'Don à :beneficiaire — :motif';
    public const DON_ENTRANT = 'Don reçu de :donateur — :motif';

    // --- Successions ---
    public const DON_SORTANT_DECES = 'Don sortant par décès — :defunt';
    public const DON_ENTRANT_DECES = 'Don entrant par décès de :defunt';
    public const LIQUIDATION_SUCCESSION = 'Liquidation succession de :defunt — :actions action(s)';
    public const VERSEMENT_SUCCESSION = 'Versement succession de :defunt — au mandataire :mandataire — :mode';
    public const VERSEMENT_SUCCESSION_REF = 'Versement succession de :defunt — au mandataire :mandataire — :mode (réf. :reference)';

    // --- Reprise de l'existant ---
    public const REPRISE_IMPORT = 'Reprise de données (import)';

    /**
     * Toutes les clés connues. Sert à la recherche et à la reprise de
     * l'historique ; une clé absente d'ici ne serait jamais retrouvée.
     *
     * @return list<string>
     */
    public static function cles(): array
    {
        return [
            self::REINVESTISSEMENT,
            self::REINVESTISSEMENT_SUCCESSION,
            self::ACHAT_SUR_SOLDE,
            self::ACHAT_COMPLEMENT,
            self::COMPLEMENT,
            self::COMPLEMENT_REF,
            self::VERSEMENT_INVESTISSEUR,
            self::VERSEMENT_INVESTISSEUR_REF,
            self::VERSEMENT_CAPITAL_RADIE,
            self::VERSEMENT_CAPITAL_RADIE_REF,
            self::DIVIDENDE,
            self::CORRECTION_BAREME,
            self::RADIATION_CAPITAL,
            self::DON_SORTANT,
            self::DON_ENTRANT,
            self::DON_SORTANT_DECES,
            self::DON_ENTRANT_DECES,
            self::LIQUIDATION_SUCCESSION,
            self::VERSEMENT_SUCCESSION,
            self::VERSEMENT_SUCCESSION_REF,
            self::REPRISE_IMPORT,
        ];
    }

    /**
     * Choisit entre la variante simple et la variante « avec référence »,
     * selon qu'une référence a été saisie ou non.
     */
    public static function selonReference(string $cleSimple, string $cleAvecReference, ?string $reference): string
    {
        return filled($reference) ? $cleAvecReference : $cleSimple;
    }

    /**
     * Phrase française, pour la colonne `observations`.
     *
     * Le français n'ayant pas de fichier de langue — c'est la langue source —
     * Lang::get rend la clé elle-même, jetons remplacés. C'est exactement ce
     * qu'on attend.
     */
    public static function francais(string $cle, array $parametres = []): string
    {
        return Lang::get($cle, self::preparer($parametres, 'fr'), 'fr');
    }

    /**
     * Texte à afficher. Sans clé, on rend la phrase brute : c'est une
     * observation saisie, elle reste dans sa langue d'origine.
     */
    public static function rendre(?string $cle, ?array $parametres, ?string $brut): string
    {
        if ($cle === null || $cle === '') {
            return (string) $brut;
        }

        return __($cle, self::preparer($parametres ?? [], app()->getLocale()));
    }

    /**
     * Clés dont la traduction, dans la langue courante, contient le terme
     * cherché. Permet de retrouver un réinvestissement en tapant « إعادة »,
     * alors que la base, elle, ne contient que le français.
     *
     * @return list<string>
     */
    public static function clesCorrespondant(string $terme): array
    {
        $terme = trim($terme);

        if ($terme === '') {
            return [];
        }

        return array_values(array_filter(
            self::cles(),
            fn ($cle) => mb_stripos(self::sansJetons(__($cle)), $terme) !== false,
        ));
    }

    /**
     * Les paramètres sont rangés bruts — date ISO, nombre, valeur d'énumération —
     * pour être mis en forme dans la langue de lecture plutôt que figés dans
     * celle de l'écriture. La mise en forme se fait par nom de paramètre ; tout
     * le reste, y compris les noms de personnes et les motifs saisis, est
     * recopié tel quel.
     */
    private static function preparer(array $parametres, string $locale): array
    {
        $prets = [];

        foreach ($parametres as $nom => $valeur) {
            $prets[$nom] = match ($nom) {
                'periode' => Carbon::parse($valeur)->locale($locale)->translatedFormat('F Y'),
                'categorie' => Lang::get(Libelles::categorie($valeur), [], $locale),
                'ancien', 'nouveau', 'actions' => Montant::format($valeur),
                default => (string) $valeur,
            };
        }

        return $prets;
    }

    /** Retire les jetons non remplacés, pour que la recherche ne porte que sur les mots. */
    private static function sansJetons(string $phrase): string
    {
        return preg_replace('/\s*:[a-z_]+\s*/u', ' ', $phrase);
    }
}
