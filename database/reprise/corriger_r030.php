<?php

/**
 * Deux corrections sur les radiations.
 *
 *   1. A0192 a reçu 100 000 CFA le 03/07/2026 pour 4 actions.
 *      La reprise n'en avait déduit qu'une, le grand livre mensuel n'en radiant
 *      qu'une ce mois-là, alors que le registre RADIEES porte bien R030 pour
 *      4 actions. Son compte tombe à zéro action.
 *
 *   2. Les observations des écritures de radiation citaient le numéro que la
 *      radiation portait avant d'être recalée sur le registre. On les réécrit
 *      à partir du numéro et de la quantité réels, cette fois avec la clé de
 *      traduction de l'application plutôt qu'en texte figé.
 *
 * Usage : php storage/app/private/reprise/corriger_r030.php
 */

require __DIR__ . '/../../vendor/autoload.php';

$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\AuditLog;
use App\Models\CompteInvestissement;
use App\Models\Radiation;
use App\Models\User;
use App\Support\Observation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

Auth::login(User::where('role', 'administrateur')->orderBy('id')->firstOrFail());

echo 'Base : ' . DB::connection()->getDatabaseName() . "\n\n";

$demander = function (string $question): bool {
    echo $question . ' [oui/non] : ';

    return in_array(strtolower(trim((string) fgets(STDIN))), ['oui', 'o', 'yes', 'y'], true);
};

// ------------------------------------------------------ 1. A0192 / R030
$compte = CompteInvestissement::whereHas('investisseur', fn ($q) => $q->where('identifiant_externe', 'A0192'))
    ->where('categorie', 'commercial')->firstOrFail();

$deduite = $compte->radiations()->where('numero_radiation', 'like', 'RAD-%')->first();

echo "1. A0192\n";
echo '   actions : ' . $compte->nombreActions() . ' | solde : ' . number_format($compte->solde(), 2, ',', ' ') . " CFA\n";
echo '   radiation déduite : ' . ($deduite
    ? $deduite->numero_radiation . ' du ' . $deduite->date_radiation->toDateString() . ', ' . $deduite->nombre_actions_radiees . ' action(s), ' . number_format($deduite->montant_total, 0, ',', ' ') . ' CFA'
    : 'aucune') . "\n";
echo "   à remplacer par : R030 du 2026-07-03, 4 actions, 100 000 CFA, Wave\n";

if ($deduite && $demander('   Appliquer ?')) {
    DB::transaction(function () use ($compte, $deduite) {
        $deduite->update([
            'numero_radiation' => 'R030',
            'date_radiation' => '2026-07-03',
            'nombre_actions_radiees' => 4,
            'prix_unitaire_action' => 25000,
            'montant_total' => 100000,
            'mode_paiement' => 'Wave',
            'observations' => 'Retrait total — registre RADIEES',
        ]);

        // Le capital crédité puis versé : les deux écritures suivent le montant.
        DB::table('ecritures_compte_financier')
            ->where('reference_type', 'radiations')->where('reference_id', $deduite->id)
            ->where('type_ecriture', 'radiation')
            ->update(['montant' => 100000, 'date_ecriture' => '2026-07-03']);

        DB::table('ecritures_compte_financier')
            ->where('reference_type', 'radiations')->where('reference_id', $deduite->id)
            ->where('type_ecriture', 'paiement')
            ->update(['montant' => -100000, 'date_ecriture' => '2026-07-03']);

        // Les soldes figés doivent être refaits : la paire s'annule, mais le solde
        // intermédiaire de la ligne de radiation, lui, change.
        $courant = 0.0;
        foreach ($compte->ecritures()->orderBy('id')->get() as $ecriture) {
            $courant = round($courant + (float) $ecriture->montant, 2);
            DB::table('ecritures_compte_financier')->where('id', $ecriture->id)->update(['solde_apres' => $courant]);
        }
    });

    AuditLog::enregistrer(
        action: 'correction',
        entite: 'radiation',
        entiteId: $deduite->id,
        apres: [
            'compte' => $compte->numero_compte,
            'numero' => 'R030',
            'date' => '2026-07-03',
            'nombre_actions' => 4,
            'montant' => 100000,
            'motif' => 'retrait total confirmé par la direction : 100 000 CFA versés pour 4 actions',
        ],
    );

    $compte = $compte->fresh();
    echo '   Fait. Actions : ' . $compte->nombreActions() . ' | solde : ' . number_format($compte->solde(), 2, ',', ' ') . " CFA\n";
}

// ------------------------------- 2. Observations des écritures de radiation
$aReecrire = [];

foreach (Radiation::with('compte')->get() as $radiation) {
    $attendu = Observation::francais(Observation::RADIATION_CAPITAL, [
        'numero' => $radiation->numero_radiation,
        'actions' => $radiation->nombre_actions_radiees,
    ]);

    $ecriture = DB::table('ecritures_compte_financier')
        ->where('reference_type', 'radiations')
        ->where('reference_id', $radiation->id)
        ->where('type_ecriture', 'radiation')
        ->first();

    if ($ecriture && $ecriture->observations !== $attendu) {
        $aReecrire[] = ['id' => $ecriture->id, 'numero' => $radiation->numero_radiation,
            'actions' => $radiation->nombre_actions_radiees, 'avant' => $ecriture->observations, 'apres' => $attendu];
    }
}

echo "\n2. Observations de radiation à réécrire : " . count($aReecrire) . "\n";

foreach (array_slice($aReecrire, 0, 4) as $r) {
    echo '     ' . mb_substr($r['avant'], 0, 48) . "\n       → " . mb_substr($r['apres'], 0, 48) . "\n";
}

if ($aReecrire !== [] && $demander('   Les réécrire ?')) {
    DB::transaction(function () use ($aReecrire) {
        foreach ($aReecrire as $r) {
            $parametres = ['numero' => $r['numero'], 'actions' => $r['actions']];

            DB::table('ecritures_compte_financier')->where('id', $r['id'])->update([
                'observations' => $r['apres'],
                'observation_cle' => Observation::RADIATION_CAPITAL,
                'observation_parametres' => json_encode($parametres, JSON_UNESCAPED_UNICODE),
            ]);
        }
    });

    AuditLog::enregistrer(
        action: 'correction',
        entite: 'ecriture',
        apres: [
            'operation' => 'observations de radiation réécrites sur le numéro réel, avec clé de traduction',
            'nombre' => count($aReecrire),
        ],
    );

    echo '   ' . count($aReecrire) . " observation(s) réécrite(s).\n";
}

echo "\nTerminé.\n";
