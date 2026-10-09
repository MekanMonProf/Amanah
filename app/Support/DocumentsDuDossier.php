<?php

namespace App\Support;

use App\Models\AchatAction;
use App\Models\EcritureCompteFinancier;
use App\Models\Investisseur;
use App\Models\Radiation;
use Illuminate\Support\Collection;

/**
 * Tous les documents qu'un dossier peut produire, en une seule liste.
 *
 * Ils existaient déjà, mais chacun n'était atteignable que depuis la ligne qui
 * l'a fait naître : l'attestation d'un achat dans l'historique des achats,
 * celle d'un paiement dans les écritures, le reçu ailleurs encore. Pour
 * retrouver « l'attestation de mon achat de janvier », il fallait savoir dans
 * quel tableau chercher, et déplier le bon.
 *
 * Rien n'est stocké : comme le relevé, chaque document se fabrique à
 * l'ouverture. Cette classe ne fait que dire ce qui existe, sous quel nom, et
 * par quelle route l'obtenir.
 *
 * Le nom de fichier annoncé ici doit être celui que les contrôleurs produisent.
 * C'est la même exigence que pour les relevés : une liste qui promet un nom et
 * en livre un autre est une liste fausse. Les tests comparent les deux.
 */
class DocumentsDuDossier
{
    /**
     * Les familles, du plus formel au plus courant, et leur intitulé.
     *
     * Une famille par document réellement distinct — un présent au waqf n'est
     * pas un achat, un réinvestissement non plus : les trois sortent d'achats
     * mais portent des titres, des textes et des noms de fichier différents.
     */
    public const FAMILLES = [
        'achat' => "Attestations d'achat",
        'reinvestissement' => 'Attestations de réinvestissement',
        'present' => 'Certificats de générosité',
        'radiation' => 'Attestations de radiation',
        'succession' => 'Attestations de liquidation de succession',
        'paiement' => 'Attestations de versement',
        'deces' => 'Attestations de versement à la succession',
        'recu' => "Reçus d'opération",
    ];

    /**
     * @return Collection<int, array{famille:string, date:\Illuminate\Support\Carbon,
     *                               reference:string, objet:string, fichier:string,
     *                               route:string, parametres:array}>
     */
    public static function pour(Investisseur $investisseur): Collection
    {
        $comptes = $investisseur->comptes()->pluck('id');

        if ($comptes->isEmpty()) {
            return collect();
        }

        $documents = collect()
            ->concat(self::achats($comptes))
            ->concat(self::radiations($comptes))
            ->concat(self::ecritures($comptes));

        // D'abord la famille, dans l'ordre déclaré ci-dessus ; puis la date, du
        // plus récent au plus ancien, comme partout ailleurs.
        $rang = array_flip(array_keys(self::FAMILLES));

        return $documents
            ->sortBy([
                fn ($a, $b) => $rang[$a['famille']] <=> $rang[$b['famille']],
                fn ($a, $b) => $b['date'] <=> $a['date'],
            ])
            ->values();
    }

    /** Les trois documents que produisent les achats, selon leur nature. */
    private static function achats(Collection $comptes): Collection
    {
        return AchatAction::whereIn('compte_id', $comptes)
            ->orderByDesc('date_achat')
            ->get()
            ->map(function (AchatAction $achat) {
                [$famille, $prefixe] = match (true) {
                    $achat->estUnPresent() => ['present', 'Certificat_Hommage_Generosite_'],
                    $achat->estUnReinvestissement() => ['reinvestissement', 'Attestation_Reinvestissement_'],
                    default => ['achat', 'Attestation_Achat_'],
                };

                return [
                    'famille' => $famille,
                    'date' => $achat->date_achat,
                    'reference' => $achat->numero_achat,
                    'objet' => __(":nombre action(s)", ['nombre' => Montant::format($achat->nombre_actions)])
                        . ' · ' . Montant::avecDevise($achat->montant),
                    'fichier' => $prefixe . $achat->numero_achat . '.pdf',
                    'route' => 'achats.attestation',
                    'parametres' => ['achat' => $achat->id],
                ];
            });
    }

    private static function radiations(Collection $comptes): Collection
    {
        return Radiation::whereIn('compte_id', $comptes)
            ->orderByDesc('date_radiation')
            ->get()
            ->map(fn (Radiation $radiation) => [
                'famille' => $radiation->estUneSuccession() ? 'succession' : 'radiation',
                'date' => $radiation->date_radiation,
                'reference' => $radiation->numero_radiation,
                'objet' => __(":nombre action(s)", ['nombre' => Montant::format($radiation->nombre_actions_radiees)])
                    . ' · ' . Montant::avecDevise($radiation->montant_total),
                'fichier' => ($radiation->estUneSuccession()
                    ? 'Attestation_Liquidation_Succession_'
                    : 'Attestation_Radiation_') . $radiation->numero_radiation . '.pdf',
                'route' => 'radiations.attestation',
                'parametres' => ['radiation' => $radiation->id],
            ]);
    }

    /**
     * Toute écriture porte un reçu ; un paiement porte en plus son attestation.
     *
     * Quand le paiement règle une succession, c'est l'attestation de succession
     * qui est servie, et elle seule : elle dit tout ce que dirait l'attestation
     * de versement ordinaire, en nommant de surcroît le défunt et le total
     * perçu. Les deux côte à côte n'offriraient qu'un choix sans objet.
     */
    private static function ecritures(Collection $comptes): Collection
    {
        $documents = collect();

        $ecritures = EcritureCompteFinancier::whereIn('compte_id', $comptes)
            ->orderByDesc('date_ecriture')
            ->get();

        foreach ($ecritures as $ecriture) {
            $numero = 'REC-' . str_pad((string) $ecriture->id, 6, '0', STR_PAD_LEFT);

            $documents->push([
                'famille' => 'recu',
                'date' => $ecriture->date_ecriture,
                'reference' => $numero,
                // Sans le signe : il appartient au grand livre, où il dit de quel
                // côté va l'argent. Ici la colonne annonce ce sur quoi porte le
                // document, et le sens est déjà dans son intitulé — un « Don
                // sortant · -50 000 CFA » se lirait comme un don négatif.
                'objet' => __(Libelles::typeEcriture($ecriture->type_ecriture))
                    . ' · ' . Montant::avecDevise(abs((float) $ecriture->montant)),
                'fichier' => 'Recu_' . $numero . '.pdf',
                'route' => 'ecritures.recu',
                'parametres' => ['ecriture' => $ecriture->id],
            ]);

            if ($ecriture->type_ecriture !== 'paiement') {
                continue;
            }

            $succession = $ecriture->reference_type === 'succession_deces';

            $documents->push([
                'famille' => $succession ? 'deces' : 'paiement',
                'date' => $ecriture->date_ecriture,
                'reference' => $numero,
                'objet' => Montant::avecDevise(abs((float) $ecriture->montant)),
                'fichier' => $succession
                    // Ce seul nom porte la date du jour : le contrôleur le
                    // construit avec now(). Annoncé ici, il vaut pour aujourd'hui.
                    ? 'Attestation_Deces_' . self::identifiantDefunt($ecriture) . '_' . now()->format('Y-m-d') . '.pdf'
                    : 'Attestation_Versement_' . $ecriture->id . '_' . $ecriture->date_ecriture->format('Y-m-d') . '.pdf',
                'route' => $succession ? 'ecritures.attestation.deces' : 'ecritures.attestation',
                'parametres' => ['ecriture' => $ecriture->id],
            ]);
        }

        return $documents;
    }

    /** Le défunt dont la succession est réglée, désigné par la référence de l'écriture. */
    private static function identifiantDefunt(EcritureCompteFinancier $ecriture): string
    {
        return (string) Investisseur::whereKey($ecriture->reference_id)
            ->value('identifiant_externe');
    }
}
