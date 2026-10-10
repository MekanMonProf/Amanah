<?php

namespace App\Support;

use App\Models\Investisseur;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Ce qu'un dossier a touché en dividendes, mois par mois.
 *
 * L'investisseur ne voyait rien de ses dividendes : son espace n'affichait que
 * « Derniers mouvements », dix écritures mêlées où un dividende ressemblait à
 * un versement. Les données existaient pourtant, période par période et compte
 * par compte — le nombre d'actions détenues ce mois-là, le bénéfice par action
 * en vigueur, le montant crédité.
 *
 * Seuls les dividendes crédités figurent ici. Un dividende calculé puis annulé
 * n'a jamais atteint le compte, et le montrer laisserait croire à un versement
 * qui n'a pas eu lieu.
 */
class HistoriqueDividendes
{
    /**
     * Du plus récent au plus ancien : on vient voir le dernier mois, puis on
     * remonte.
     *
     * @return Collection<int, array{annee:int, periode:string, libelle:string,
     *                               lignes:Collection, actions:int, montant:float}>
     */
    public static function pour(Investisseur $investisseur): Collection
    {
        $comptes = $investisseur->comptes()->get()->keyBy('id');

        if ($comptes->isEmpty()) {
            return collect();
        }

        $mois = \App\Models\Dividende::whereIn('compte_id', $comptes->keys())
            ->where('statut', 'credite')
            ->orderByDesc('periode')
            ->get()
            ->groupBy(fn ($dividende) => $dividende->periode->format('Y-m'))
            ->map(fn ($dividendes, $mois) => self::decrireLeMois($dividendes, $mois, $comptes))
            ->values();

        return self::comparerLesTaux($mois);
    }

    /**
     * La hausse ou la baisse du bénéfice par action, d'un mois sur l'autre.
     *
     * La comparaison suit chaque compte séparément, et non le total du mois :
     * le commercial et le waqf ont chacun leur barème, et mêler leurs taux ne
     * dirait rien de ni l'un ni l'autre. La flèche s'affiche donc sur la ligne,
     * à côté du taux qu'elle qualifie.
     *
     * « Le mois d'avant » est le mois précédent où ce compte a touché quelque
     * chose, et non celui du calendrier : un compte ouvert en cours d'année,
     * ou un mois sans distribution, laisse des trous. Comparer à un mois absent
     * reviendrait à comparer à zéro et afficherait une envolée qui n'a pas eu
     * lieu.
     */
    private static function comparerLesTaux(Collection $mois): Collection
    {
        $precedents = [];
        $enrichis = [];

        // La liste va du plus récent au plus ancien ; on la parcourt à l'envers
        // pour garder en mémoire, compte par compte, le dernier taux rencontré,
        // puis on la remet dans son ordre de lecture.
        //
        // On reconstruit plutôt qu'on ne modifie : les éléments sont des
        // tableaux, que PHP copie par valeur — une écriture dans la boucle ne
        // reviendrait pas dans la collection.
        foreach ($mois->reverse() as $periode) {
            $periode['lignes'] = $periode['lignes']->map(function (array $ligne) use (&$precedents) {
                $identifiant = $ligne['compte']?->id ?? 0;
                $precedent = $precedents[$identifiant] ?? null;

                $ligne['variation'] = $precedent === null ? null : self::ecart($ligne['taux'], $precedent);
                $precedents[$identifiant] = $ligne['taux'];

                return $ligne;
            });

            $enrichis[] = $periode;
        }

        return collect(array_reverse($enrichis));
    }

    /** @return array{sens:string, pourcentage:?float, precedent:float} */
    private static function ecart(float $montant, float $precedent): array
    {
        $sens = match (true) {
            $montant > $precedent => 'hausse',
            $montant < $precedent => 'baisse',
            default => 'stable',
        };

        return [
            'sens' => $sens,
            // Un mois précédent à zéro ne donne pas de pourcentage : on garde la
            // flèche, qui dit le sens, et on tait le rapport, qui n'existe pas.
            'pourcentage' => $precedent > 0 ? ($montant - $precedent) / $precedent * 100 : null,
            'precedent' => $precedent,
        ];
    }

    /**
     * Un mois rassemble les dividendes de tous les comptes du dossier.
     *
     * Les deux catégories suivent des barèmes distincts : les afficher séparément
     * dit pourquoi deux lignes d'un même mois ne portent pas le même taux, là où
     * un total les aurait confondues.
     */
    private static function decrireLeMois(Collection $dividendes, string $mois, Collection $comptes): array
    {
        $debut = \Illuminate\Support\Carbon::createFromFormat('Y-m-d', $mois . '-01');

        return [
            'annee' => (int) $debut->year,
            'periode' => $mois,
            'libelle' => Str::ucfirst($debut->translatedFormat('F Y')),
            'lignes' => $dividendes->map(fn ($dividende) => [
                'compte' => $comptes[$dividende->compte_id] ?? null,
                'actions' => (int) $dividende->nombre_actions,
                'taux' => (float) $dividende->benefice_par_action,
                'montant' => (float) $dividende->montant_calcule,
            ])->values(),
            'actions' => (int) $dividendes->sum('nombre_actions'),
            'montant' => (float) $dividendes->sum('montant_calcule'),
        ];
    }

    /**
     * Le cumul, pour le bandeau : total perçu, nombre de mois servis, et le
     * dernier montant en date.
     *
     * @return array{total:float, mois:int, dernier:?float, dernierePeriode:?string}
     */
    public static function cumul(Collection $historique): array
    {
        $premier = $historique->first();

        return [
            'total' => (float) $historique->sum('montant'),
            'mois' => $historique->count(),
            'dernier' => $premier['montant'] ?? null,
            'dernierePeriode' => $premier['libelle'] ?? null,
        ];
    }
}
