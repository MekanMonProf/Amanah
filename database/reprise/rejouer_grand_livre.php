<?php

/**
 * Rejoue le grand livre mensuel du classeur dans la plateforme, mois par mois.
 *
 * Pour chaque mois et chaque compte : dividende, réinvestissement, paiement,
 * radiation, puis — s'il reste un écart avec le solde du classeur — une écriture
 * d'ajustement étiquetée. Toutes les écritures passent par ajouterEcriture(),
 * seul point d'entrée autorisé pour faire bouger un solde.
 *
 * Usage : php ... rejouer_grand_livre.php <base_attendue> [--sec]
 */

use App\Models\AchatAction;
use App\Models\AuditLog;
use App\Models\BaremeDividende;
use App\Models\CompteInvestissement;
use App\Models\EcritureCompteFinancier;
use App\Models\Investisseur;
use App\Models\Radiation;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

$baseAttendue = $argv[1] ?? 'amanah_essai_import';
$simulation = in_array('--sec', $argv, true);

if (DB::connection()->getDatabaseName() !== $baseAttendue) {
    exit('ARRÊT : base ' . DB::connection()->getDatabaseName() . " au lieu de {$baseAttendue}.\n");
}

$grandLivre = json_decode(file_get_contents(__DIR__ . '/grand_livre.json'), true);
$administrateur = User::where('role', 'administrateur')->orderBy('id')->firstOrFail();
Auth::login($administrateur);

echo 'Base : ' . DB::connection()->getDatabaseName() . ($simulation ? "  (SIMULATION)\n" : "\n");

$PRIX = 25000.0;
$chrono = microtime(true);

// ------------------------------------------------------------- Nettoyage
// Les lignes agrégées du premier import sont remplacées par le détail mensuel.
DB::transaction(function () {
    $achatsAgreges = AchatAction::where('numero_achat', 'like', 'BEN-%')->get();
    $comptesTouches = $achatsAgreges->pluck('compte_id')->unique();
    echo '  suppression de ' . $achatsAgreges->count() . " achats agrégés « BEN- »\n";
    AchatAction::whereIn('id', $achatsAgreges->pluck('id'))->delete();

    $ajustements = EcritureCompteFinancier::where('type_ecriture', 'ajustement')
        ->where('observations', 'like', 'Solde repris du fichier Excel%')
        ->get();
    echo '  suppression de ' . $ajustements->count() . " écritures d'ajustement de reprise\n";
    EcritureCompteFinancier::whereIn('id', $ajustements->pluck('id'))->delete();
});

// --------------------------------------------------------------- Barèmes
$tauxParMois = [];
foreach ($grandLivre as $mois => $comptes) {
    $taux = 0.0;
    foreach ($comptes as $d) {
        $taux = max($taux, (float) $d['taux']);
    }
    $tauxParMois[$mois] = $taux;
}

$baremesCrees = 0;
foreach ($tauxParMois as $mois => $taux) {
    if ($taux <= 0) {
        continue;   // mois sans partage déclaré
    }

    foreach (['commercial', 'waqf'] as $categorie) {
        BaremeDividende::firstOrCreate(
            ['periode' => $mois . '-01', 'categorie' => $categorie],
            ['benefice_par_action' => $taux, 'fixe_par' => $administrateur->id]
        );
        $baremesCrees++;
    }
}
echo "  {$baremesCrees} barème(s) enregistré(s)\n\n";

// ------------------------------------------------------------- Comptes
$investisseurs = Investisseur::pluck('id', 'identifiant_externe')
    ->mapWithKeys(fn ($id, $cle) => [strtoupper($cle) => $id])->all();

$comptes = [];
foreach (CompteInvestissement::with('investisseur')->get() as $compte) {
    $comptes[strtoupper($compte->investisseur->identifiant_externe) . '|' . $compte->categorie] = $compte;
}

$compteDe = function (string $identifiant, string $categorie) use (&$comptes, $investisseurs): ?CompteInvestissement {
    $cle = $identifiant . '|' . $categorie;

    if (isset($comptes[$cle])) {
        return $comptes[$cle];
    }

    if (! isset($investisseurs[$identifiant])) {
        return null;
    }

    $compte = Investisseur::find($investisseurs[$identifiant])->compteOuCree($categorie);
    $comptes[$cle] = $compte;

    return $compte;
};

// -------------------------------------------------------------- Rejeu
$stats = ['dividendes' => 0, 'reinvestissements' => 0, 'paiements' => 0, 'radiations' => 0, 'ajustements' => 0, 'montant_ajuste' => 0.0];
$inconnus = [];
$ajustementsDetail = [];

foreach ($grandLivre as $mois => $lignes) {
    $periode = $mois . '-01';
    $finDeMois = Carbon::parse($periode)->endOfMonth()->toDateString();
    ksort($lignes);

    DB::transaction(function () use ($lignes, $mois, $periode, $finDeMois, $compteDe, $PRIX, &$stats, &$inconnus, &$ajustementsDetail, $administrateur) {
        foreach ($lignes as $identifiant => $d) {
            foreach ([['commercial', 'com'], ['waqf', 'waqf']] as [$categorie, $suffixe]) {
                $actions = (int) $d['actions_' . $suffixe];
                $benefice = (float) $d['benefice_' . $suffixe];
                $reinvestissement = (int) $d['reinv_' . $suffixe];
                $radiation = (int) $d['radiation_' . $suffixe];
                $soldeVise = (float) $d['solde_fin_' . $suffixe];
                $paiement = $categorie === 'commercial' ? (float) $d['paiement'] : 0.0;

                $rienAFaire = $benefice == 0 && $reinvestissement === 0 && $radiation === 0
                    && $paiement == 0 && $soldeVise == 0 && $actions === 0;

                if ($rienAFaire) {
                    continue;
                }

                $compte = $compteDe($identifiant, $categorie);

                if (! $compte) {
                    $inconnus[$identifiant] = true;
                    continue;
                }

                // 1) Dividende de la période
                if ($benefice > 0 && $actions > 0) {
                    $dejaPresent = $compte->dividendes()->where('periode', $periode)->exists();

                    if (! $dejaPresent) {
                        $dividende = $compte->dividendes()->create([
                            'periode' => $periode,
                            'nombre_actions' => $actions,
                            'benefice_par_action' => $d['taux'],
                            'montant_calcule' => $benefice,
                            'statut' => 'credite',
                        ]);

                        $compte->ajouterEcriture(
                            type: 'dividende',
                            montant: $benefice,
                            dateEcriture: $periode,
                            referenceType: 'dividendes',
                            referenceId: $dividende->id,
                            observationCle: \App\Support\Observation::DIVIDENDE,
                            observationParametres: ['periode' => $periode],
                            userId: $administrateur->id,
                        );

                        $stats['dividendes']++;
                    }
                }

                // 2) Réinvestissement du mois
                if ($reinvestissement > 0) {
                    $montant = $reinvestissement * $PRIX;

                    if ($finDeMois < $compte->date_ouverture->toDateString()) {
                        $compte->update(['date_ouverture' => $finDeMois]);
                    }

                    $achat = $compte->achats()->create([
                        'numero_achat' => 'REINV-' . $identifiant . '-' . str_replace('-', '', $mois) . '-' . ($categorie === 'commercial' ? 'C' : 'W'),
                        'date_achat' => $finDeMois,
                        'type_achat' => 'benefice',
                        'nombre_actions' => $reinvestissement,
                        'prix_unitaire' => $PRIX,
                        'montant' => $montant,
                        'observations' => 'Réinvestissement automatique — reprise du fichier Excel',
                        'saisi_par' => $administrateur->id,
                    ]);

                    $compte->ajouterEcriture(
                        type: 'achat_action',
                        montant: -$montant,
                        dateEcriture: $finDeMois,
                        referenceType: 'achats_actions',
                        referenceId: $achat->id,
                        observations: 'Réinvestissement automatique — reprise du fichier Excel',
                        userId: $administrateur->id,
                    );

                    $stats['reinvestissements']++;
                }

                // 3) Versement à l'investisseur
                if ($paiement > 0) {
                    $compte->ajouterEcriture(
                        type: 'paiement',
                        montant: -$paiement,
                        dateEcriture: $d['paiement_date'] ?: $finDeMois,
                        observations: 'Versement à l\'investisseur — reprise du fichier Excel'
                            . ($d['paiement_mode'] ? ' (' . $d['paiement_mode'] . ')' : ''),
                        userId: $administrateur->id,
                    );

                    $stats['paiements']++;
                }

                // 4) Radiation : le capital radié est crédité puis immédiatement versé,
                //    comme dans l'application — le classeur ne le laisse jamais en solde.
                if ($radiation > 0) {
                    $montant = $radiation * $PRIX;
                    $numero = $d['radiation_numero'] ?: ('RAD-' . $identifiant . '-' . str_replace('-', '', $mois));

                    if (Radiation::where('numero_radiation', $numero)->exists()) {
                        $numero = 'RAD-' . $identifiant . '-' . str_replace('-', '', $mois) . '-' . ($categorie === 'commercial' ? 'C' : 'W');
                    }

                    $ligneRadiation = $compte->radiations()->create([
                        'numero_radiation' => $numero,
                        'date_radiation' => $finDeMois,
                        'nombre_actions_radiees' => $radiation,
                        'prix_unitaire_action' => $PRIX,
                        'montant_total' => $montant,
                        'observations' => 'Reprise du fichier Excel',
                    ]);

                    $compte->ajouterEcriture(
                        type: 'radiation',
                        montant: $montant,
                        dateEcriture: $finDeMois,
                        referenceType: 'radiations',
                        referenceId: $ligneRadiation->id,
                        observations: "Radiation {$numero} — {$radiation} action(s), capital disponible pour versement",
                        userId: $administrateur->id,
                    );

                    $compte->ajouterEcriture(
                        type: 'paiement',
                        montant: -$montant,
                        dateEcriture: $finDeMois,
                        referenceType: 'radiations',
                        referenceId: $ligneRadiation->id,
                        observations: 'Versement du capital radié — reprise du fichier Excel',
                        userId: $administrateur->id,
                    );

                    $stats['radiations']++;
                }

                // 5) Ce qui reste inexpliqué devient un ajustement, jamais un arrondi silencieux.
                // Sous le franc, c'est la dérive d'arrondi du classeur (qui garde toutes ses
                // décimales là où la base s'arrête au centime) : elle est soldée en une seule
                // écriture par compte à la fin, pas en bruit mensuel.
                $ecart = round($soldeVise - $compte->solde(), 2);

                if (abs($ecart) >= 1) {
                    $compte->ajouterEcriture(
                        type: 'ajustement',
                        montant: $ecart,
                        dateEcriture: $finDeMois,
                        observations: 'Correction reprise du fichier Excel — ' . $mois,
                        userId: $administrateur->id,
                    );

                    $stats['ajustements']++;
                    $stats['montant_ajuste'] += $ecart;

                    if (abs($ecart) > 1) {
                        $ajustementsDetail[] = sprintf('%s %s %-11s %+14s CFA', $mois, $identifiant, $categorie, number_format($ecart, 2, ',', ' '));
                    }
                }
            }
        }
    });

    echo '  ' . $mois . ' traité  (' . number_format(microtime(true) - $chrono, 0) . "s)\n";
}

// ------------------------------------------------- Solde des arrondis
// Une seule écriture par compte pour retomber exactement sur le classeur.
$derniersSoldes = [];
foreach (array_reverse($grandLivre, true) as $mois => $lignes) {
    foreach ($lignes as $identifiant => $d) {
        foreach ([['commercial', 'com'], ['waqf', 'waqf']] as [$categorie, $suffixe]) {
            $cle = $identifiant . '|' . $categorie;
            if (! array_key_exists($cle, $derniersSoldes)) {
                $derniersSoldes[$cle] = (float) $d['solde_fin_' . $suffixe];
            }
        }
    }
}

$stats['arrondis'] = 0;
$stats['montant_arrondi'] = 0.0;
$dernierMois = Carbon::parse(array_key_last($grandLivre) . '-01')->endOfMonth()->toDateString();

DB::transaction(function () use ($derniersSoldes, $compteDe, $dernierMois, &$stats, $administrateur) {
    foreach ($derniersSoldes as $cle => $soldeVise) {
        [$identifiant, $categorie] = explode('|', $cle);
        $compte = $compteDe($identifiant, $categorie);

        if (! $compte) {
            continue;
        }

        $ecart = round($soldeVise - $compte->solde(), 2);

        if (abs($ecart) >= 0.01) {
            $compte->ajouterEcriture(
                type: 'ajustement',
                montant: $ecart,
                dateEcriture: $dernierMois,
                observations: 'Arrondi au centime — reprise du fichier Excel',
                userId: $administrateur->id,
            );

            $stats['arrondis']++;
            $stats['montant_arrondi'] += $ecart;
        }
    }
});

echo "\nRésultat :\n";
foreach ($stats as $cle => $valeur) {
    echo '  ' . str_pad($cle, 18) . (is_float($valeur) ? number_format($valeur, 2, ',', ' ') . ' CFA' : $valeur) . "\n";
}

if ($inconnus) {
    echo "\nIdentifiants du grand livre absents de la base : " . implode(', ', array_slice(array_keys($inconnus), 0, 15)) . "\n";
}

if ($ajustementsDetail) {
    echo "\nAjustements supérieurs à 1 CFA (" . count($ajustementsDetail) . ") :\n";
    foreach (array_slice($ajustementsDetail, 0, 20) as $a) {
        echo '  ' . $a . "\n";
    }
}

AuditLog::enregistrer(
    action: 'import',
    entite: 'ecriture',
    apres: [
        'operation' => 'reprise du grand livre mensuel',
        'source' => 'Gestion_Actionnaires_2024-2025.xlsm + Gestion_Actionnaires_2026.xlsm',
        'mois' => count($grandLivre),
        'dividendes' => $stats['dividendes'],
        'reinvestissements' => $stats['reinvestissements'],
        'paiements' => $stats['paiements'],
        'radiations' => $stats['radiations'],
        'ajustements' => $stats['ajustements'],
    ],
);

echo "\nDurée : " . number_format(microtime(true) - $chrono, 0) . " s\n";
