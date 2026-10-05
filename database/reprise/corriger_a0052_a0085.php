<?php

/**
 * Deux corrections de saisie, tranchées par la direction.
 *
 * À lancer depuis la racine du projet :
 *   php storage/app/private/reprise/corriger_a0052_a0085.php
 *
 *   A0052 — ACH-478 n'est pas un doublon d'ACH-208 : c'est un complément, le même
 *   jour et pour le même montant. La reprise l'avait écarté sur ces ressemblances.
 *   Son absence expliquait les trois dividendes calculés sur plus d'actions que le
 *   compte n'en détenait : le classeur, lui, comptait bien cette action.
 *
 *   A0085 — l'actionnaire a récupéré son action et son solde le 16/09/2025. Le
 *   versement du solde porte déjà cette date ; la radiation, elle, avait été datée
 *   de la fin du mois où le classeur la faisait apparaître. S'y ajoutaient une
 *   seconde radiation déduite par la reprise — d'où le nombre d'actions négatif —
 *   et un ajustement d'août 2026 recopiant un solde que le classeur a continué
 *   d'alimenter après son départ.
 */

require __DIR__ . '/../../vendor/autoload.php';

$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\AuditLog;
use App\Models\CompteInvestissement;
use App\Models\Investisseur;
use App\Models\Radiation;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

$administrateur = User::where('role', 'administrateur')->orderBy('id')->firstOrFail();
Auth::login($administrateur);

echo 'Base : ' . DB::connection()->getDatabaseName() . "\n\n";

$demander = function (string $question): bool {
    echo $question . ' [oui/non] : ';

    return in_array(strtolower(trim((string) fgets(STDIN))), ['oui', 'o', 'yes', 'y'], true);
};

$compteDe = fn (string $identifiant, string $categorie) => CompteInvestissement::whereHas(
    'investisseur',
    fn ($q) => $q->where('identifiant_externe', $identifiant)
)->where('categorie', $categorie)->firstOrFail();

// ------------------------------------------------- A0052 : ACH-478 manquant
$compte = $compteDe('A0052', 'commercial');

echo '1. A0052 — ' . $compte->investisseur->nom . ' ' . $compte->investisseur->prenom . "\n";
echo '   actions actuelles : ' . $compte->nombreActions() . " (le classeur en annonce 88)\n";
echo '   ACH-478 présent   : ' . ($compte->achats()->where('numero_achat', 'ACH-478')->exists() ? 'oui' : 'NON') . "\n";

if (! $compte->achats()->where('numero_achat', 'ACH-478')->exists()
    && $demander('   Enregistrer ACH-478 (complément du 01/08/2025, 1 action, 25 000 CFA) ?')) {

    // Pas d'écriture : comme les autres achats repris du classeur, l'argent n'a jamais
    // transité par le solde du compte — il a été versé puis employé dans le même geste.
    $achat = $compte->achats()->create([
        'numero_achat' => 'ACH-478',
        'date_achat' => '2025-08-01',
        'type_achat' => 'complement',
        'nombre_actions' => 1,
        'prix_unitaire' => 25000,
        'montant' => 25000,
        'mode_paiement' => 'Wave',
        'observations' => 'Complément — ligne 481 du classeur, écartée à tort comme doublon lors de la reprise',
        'saisi_par' => $administrateur->id,
    ]);

    AuditLog::enregistrer(
        action: 'creation',
        entite: 'achat',
        entiteId: $achat->id,
        apres: [
            'numero_achat' => 'ACH-478',
            'compte' => $compte->numero_compte,
            'nombre_actions' => 1,
            'montant' => 25000,
            'motif' => 'complément écarté à tort comme doublon lors de la reprise du classeur',
        ],
    );

    echo '   Enregistré. Actions : ' . $compte->fresh()->nombreActions() . "\n";
}

// ------------------------------------------------------ A0085 : le départ
$compte = $compteDe('A0085', 'commercial');
const DATE_DEPART = '2025-09-16';

echo "\n2. A0085 — " . $compte->investisseur->nom . ' ' . $compte->investisseur->prenom . "\n";
echo '   actions : ' . $compte->nombreActions() . ' | solde : ' . number_format($compte->solde(), 2, ',', ' ') . " CFA\n";

$radiationDeduite = $compte->radiations()->where('numero_radiation', 'like', 'RAD-A0085-%')->first();
$radiationReelle = $compte->radiations()->where('numero_radiation', 'R085')->first();
$ajustement = $compte->ecritures()->where('type_ecriture', 'ajustement')
    ->where('observations', 'like', 'Correction reprise%')->orderByDesc('id')->first();

echo '   radiation déduite à retirer : ' . ($radiationDeduite?->numero_radiation ?? 'aucune') . "\n";
echo '   radiation réelle R085 datée  : ' . ($radiationReelle?->date_radiation->toDateString() ?? 'absente')
    . ' → à recaler au ' . DATE_DEPART . "\n";
echo '   ajustement à retirer         : ' . ($ajustement ? number_format($ajustement->montant, 2, ',', ' ') . ' CFA du ' . $ajustement->date_ecriture->toDateString() : 'aucun') . "\n";

if ($demander('   Appliquer ?')) {
    DB::transaction(function () use ($compte, $radiationDeduite, $radiationReelle, $ajustement, $administrateur) {
        if ($radiationDeduite) {
            $compte->ecritures()
                ->where('reference_type', 'radiations')
                ->where('reference_id', $radiationDeduite->id)
                ->delete();
            $radiationDeduite->delete();
        }

        $ajustement?->delete();

        if ($radiationReelle) {
            $compte->ecritures()
                ->where('reference_type', 'radiations')
                ->where('reference_id', $radiationReelle->id)
                ->update(['date_ecriture' => DATE_DEPART]);

            $radiationReelle->update(['date_radiation' => DATE_DEPART]);
        }

        // Le reliquat de centimes laissé par l'arrondi du versement part avec le reste :
        // l'actionnaire a tout récupéré, le compte doit se lire à zéro.
        $reliquat = round($compte->fresh()->solde(), 2);

        if (abs($reliquat) >= 0.01) {
            $compte->ajouterEcriture(
                type: 'paiement',
                montant: -$reliquat,
                dateEcriture: DATE_DEPART,
                observations: 'Versement du reliquat — retrait total du ' . DATE_DEPART,
                userId: $administrateur->id,
            );
        }
    });

    AuditLog::enregistrer(
        action: 'correction',
        entite: 'compte',
        entiteId: $compte->id,
        apres: [
            'compte' => $compte->numero_compte,
            'motif' => 'retrait total de l\'actionnaire le ' . DATE_DEPART . ' : radiation recalée, radiation déduite et ajustement de reprise retirés',
        ],
    );

    $compte = $compte->fresh();
    echo '   Appliqué. Actions : ' . $compte->nombreActions() . ' | solde : ' . number_format($compte->solde(), 2, ',', ' ') . " CFA\n";
}

echo "\nTerminé.\n";
