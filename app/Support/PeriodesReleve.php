<?php

namespace App\Support;

use App\Models\Investisseur;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Les mois pour lesquels un relevé a quelque chose à dire.
 *
 * L'ancienne application déposait un fichier PDF par mois et par actionnaire :
 * la liste des relevés y était la liste des fichiers déposés. AMANAH ne dépose
 * rien — elle fabrique le relevé à la demande, depuis ses propres écritures. La
 * liste doit donc se déduire des données plutôt que d'un dossier.
 *
 * Un mois y figure dès qu'il porte un mouvement : un achat, une écriture sur le
 * compte financier, ou un dividende. Les mois vides sont écartés. Proposer le
 * relevé d'un mois où rien ne s'est passé donnerait un PDF de titres sans
 * lignes, et noierait les mois qui comptent — un actionnaire entré en 2025 en
 * aurait des dizaines à faire défiler avant d'atteindre le premier qui parle.
 */
class PeriodesReleve
{
    /** Les tables qui portent un mouvement, et la colonne qui le date. */
    private const SOURCES = [
        'achats_actions' => 'date_achat',
        'ecritures_compte_financier' => 'date_ecriture',
        'dividendes' => 'periode',
    ];

    /**
     * Les mois en français sans accent, comme les nommait l'ancienne application.
     *
     * Le nom du fichier garde cette forme dans les trois langues : un actionnaire
     * qui a déjà des « Releve_A0387_Aout_2026.pdf » dans son dossier de
     * téléchargements retrouve les nouveaux au même endroit, triés avec les
     * anciens. L'ASCII évite de plus les noms abîmés par l'en-tête HTTP.
     */
    private const MOIS = [
        1 => 'Janvier', 'Fevrier', 'Mars', 'Avril', 'Mai', 'Juin',
        'Juillet', 'Aout', 'Septembre', 'Octobre', 'Novembre', 'Decembre',
    ];

    /**
     * Du plus récent au plus ancien : on vient chercher le dernier relevé, pas
     * le premier.
     *
     * @return Collection<int, array{annee:int, mois:int, debut:string, fin:string,
     *                               libelle:string, fichier:string, mouvements:int}>
     */
    public static function pour(Investisseur $investisseur): Collection
    {
        $comptes = $investisseur->comptes()->pluck('id');

        if ($comptes->isEmpty()) {
            return collect();
        }

        $parMois = [];

        foreach (self::SOURCES as $table => $colonne) {
            $lignes = DB::table($table)
                ->selectRaw("DATE_FORMAT(`{$colonne}`, '%Y-%m') AS mois, COUNT(*) AS nombre")
                ->whereIn('compte_id', $comptes)
                ->whereNotNull($colonne)
                ->groupBy('mois')
                ->get();

            foreach ($lignes as $ligne) {
                $parMois[$ligne->mois] = ($parMois[$ligne->mois] ?? 0) + (int) $ligne->nombre;
            }
        }

        krsort($parMois);

        return collect($parMois)
            ->map(fn (int $mouvements, string $mois) => self::decrire($investisseur, $mois, $mouvements))
            ->values();
    }

    /** Le relevé du mois : ses bornes, son intitulé, le nom du fichier produit. */
    private static function decrire(Investisseur $investisseur, string $mois, int $mouvements): array
    {
        $debut = Carbon::createFromFormat('Y-m-d', $mois . '-01')->startOfMonth();
        $fin = $debut->copy()->endOfMonth();

        return [
            'annee' => (int) $debut->year,
            'mois' => (int) $debut->month,
            'debut' => $debut->toDateString(),
            'fin' => $fin->toDateString(),
            // Str::ucfirst plutôt que ucfirst : en arabe, le premier caractère
            // tient sur plusieurs octets, et la version PHP travaille à l'octet.
            'libelle' => Str::ucfirst($debut->translatedFormat('F Y')),
            'fichier' => self::nomDeFichier($investisseur, $debut->toDateString(), $fin->toDateString()),
            'mouvements' => $mouvements,
        ];
    }

    /**
     * Le nom du fichier produit pour une période donnée.
     *
     * La liste des relevés annonce ce nom avant que le PDF existe ; c'est donc
     * ici, et non dans le contrôleur, qu'il se décide — sinon la liste promet
     * un fichier et le téléchargement en livre un autre.
     *
     * Une période qui épouse exactement un mois reprend la forme de l'ancienne
     * application. Toute autre période garde l'ancien nom daté du jour : elle
     * n'a pas de mois à nommer, et deux extractions différentes ne doivent pas
     * se recouvrir dans le dossier de téléchargements.
     */
    public static function nomDeFichier(Investisseur $investisseur, ?string $debut, ?string $fin): string
    {
        if ($mois = self::moisExact($debut, $fin)) {
            return sprintf(
                'Releve_%s_%s_%d.pdf',
                $investisseur->identifiant_externe,
                self::MOIS[$mois->month],
                $mois->year,
            );
        }

        $suffixe = $debut || $fin ? '_periode' : '';

        return 'Releve_' . $investisseur->identifiant_externe . $suffixe . '_' . now()->format('Y-m-d') . '.pdf';
    }

    /** Le mois, si les deux bornes sont exactement son premier et son dernier jour. */
    private static function moisExact(?string $debut, ?string $fin): ?Carbon
    {
        if (! $debut || ! $fin) {
            return null;
        }

        try {
            // Les bornes viennent de l'URL : n'importe qui peut y écrire n'importe
            // quoi. Une date illisible ne vaut pas une erreur — elle vaut le nom
            // générique, et le relevé se fabrique quand même.
            $premier = Carbon::parse($debut);
            $dernier = Carbon::parse($fin);
        } catch (\Throwable) {
            return null;
        }

        $memeMois = $premier->format('Y-m') === $dernier->format('Y-m');

        return $memeMois && $premier->day === 1 && $dernier->day === $dernier->daysInMonth
            ? $premier
            : null;
    }
}
