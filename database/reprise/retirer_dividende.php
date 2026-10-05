<?php

/**
 * Retire un dividende versé à tort sur un compte, et refait la chaîne des soldes.
 *
 * Sert aux dividendes que le classeur a calculés sur des actions que le compte ne
 * détenait plus. La règle de sortie en couvre la plupart — un actionnaire parti en
 * cours de mois garde le mois — mais pas ceux versés les mois suivants.
 *
 * Usage : php storage/app/private/reprise/retirer_dividende.php <identifiant> <AAAA-MM>
 *   ex.  php storage/app/private/reprise/retirer_dividende.php A0058 2025-04
 */

require __DIR__ . '/../../vendor/autoload.php';

$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\AuditLog;
use App\Models\CompteInvestissement;
use App\Models\Dividende;
use App\Models\ParametreDividende;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

$identifiant = strtoupper($argv[1] ?? '');
$mois = $argv[2] ?? '';
$categorie = $argv[3] ?? 'commercial';

if (! preg_match('/^A\d+$/', $identifiant) || ! preg_match('/^\d{4}-\d{2}$/', $mois)) {
    exit("Usage : php storage/app/private/reprise/retirer_dividende.php <identifiant> <AAAA-MM> [categorie]\n");
}

Auth::login(User::where('role', 'administrateur')->orderBy('id')->firstOrFail());

$compte = CompteInvestissement::whereHas('investisseur', fn ($q) => $q->where('identifiant_externe', $identifiant))
    ->where('categorie', $categorie)->first();

if (! $compte) {
    exit("Compte {$identifiant}-{$categorie} introuvable.\n");
}

$periode = $mois . '-01';
$dividende = Dividende::where('compte_id', $compte->id)->where('periode', $periode)->first();

if (! $dividende) {
    exit("Aucun dividende de {$mois} sur {$compte->numero_compte}.\n");
}

$ecritures = $compte->ecritures()
    ->where('reference_type', 'dividendes')
    ->where('reference_id', $dividende->id)
    ->get();

$delai = ParametreDividende::actuel()->delai_radiation_jours;

echo 'Base : ' . DB::connection()->getDatabaseName() . "\n\n";
echo $identifiant . ' — ' . trim($compte->investisseur->nom . ' ' . $compte->investisseur->prenom) . "\n";
echo '  actions aujourd\'hui          : ' . $compte->nombreActions() . "\n";
echo '  actions retenues pour ' . $mois . '   : ' . $compte->actionsRetenuesPourDividende($dividende->periode, $delai)
    . ' (règle de sortie : ' . $delai . " jour(s))\n";
echo '  solde actuel                 : ' . number_format($compte->solde(), 2, ',', ' ') . " CFA\n\n";
echo "  dividende à retirer :\n";
echo '    ' . $dividende->periode->format('Y-m') . ' — ' . $dividende->nombre_actions
    . ' action(s), ' . number_format($dividende->montant_calcule, 2, ',', ' ') . " CFA\n";

foreach ($ecritures as $e) {
    echo '    écriture #' . $e->id . ' du ' . $e->date_ecriture->toDateString()
        . ' : ' . number_format($e->montant, 2, ',', ' ') . " CFA\n";
}

echo '  solde après retrait          : ' . number_format($compte->solde() - $ecritures->sum('montant'), 2, ',', ' ') . " CFA\n";
echo "\nRetirer ? [oui/non] : ";

if (! in_array(strtolower(trim((string) fgets(STDIN))), ['oui', 'o', 'yes', 'y'], true)) {
    exit("Annulé.\n");
}

$montant = (float) $dividende->montant_calcule;

DB::transaction(function () use ($compte, $dividende, $ecritures) {
    $compte->ecritures()->whereIn('id', $ecritures->pluck('id'))->delete();
    $dividende->delete();

    // Les soldes figés des écritures suivantes doivent être refaits : on rejoue la
    // chaîne dans l'ordre d'insertion, seul ordre qui fasse foi.
    $courant = 0.0;

    foreach ($compte->ecritures()->orderBy('id')->get() as $ecriture) {
        $courant = round($courant + (float) $ecriture->montant, 2);
        DB::table('ecritures_compte_financier')->where('id', $ecriture->id)->update(['solde_apres' => $courant]);
    }
});

AuditLog::enregistrer(
    action: 'correction',
    entite: 'compte',
    entiteId: $compte->id,
    apres: [
        'compte' => $compte->numero_compte,
        'operation' => 'retrait du dividende ' . $mois,
        'montant' => $montant,
        'motif' => 'dividende calculé sur des actions que le compte ne détenait plus à cette période',
    ],
);

$compte = $compte->fresh();

echo "\nRetiré. Actions : " . $compte->nombreActions()
    . ' | solde : ' . number_format($compte->solde(), 2, ',', ' ') . " CFA\n";
