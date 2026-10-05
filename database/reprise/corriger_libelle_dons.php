<?php

/**
 * Rend aux dons au Waqf leur nom.
 *
 * La reprise avait enregistré ces sorties en « ajustement », avec pour observation
 * « Correction reprise du fichier Excel » : c'est tout ce que le grand livre mensuel
 * permettait d'en dire, le classeur ne les expliquant nulle part. Elles figurent en
 * réalité dans la colonne « DON AU WAQF » de la feuille ACTIONNAIRES, au franc près
 * — 209 172 CFA sur onze actionnaires.
 *
 * Ces dons financent le fonctionnement de l'œuvre : rien n'arrive sur un compte de
 * placement en face, il n'y a donc pas de contrepartie à écrire. Seuls le type et
 * l'observation changent ; aucun solde ne bouge.
 *
 * Usage : php storage/app/private/reprise/corriger_libelle_dons.php
 */

require __DIR__ . '/../../vendor/autoload.php';

$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\AuditLog;
use App\Models\User;
use App\Support\Observation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

Auth::login(User::where('role', 'administrateur')->orderBy('id')->firstOrFail());

echo 'Base : ' . DB::connection()->getDatabaseName() . "\n\n";

$aRenommer = DB::table('ecritures_compte_financier as e')
    ->join('comptes_investissement as c', 'c.id', '=', 'e.compte_id')
    ->join('investisseurs as i', 'i.id', '=', 'c.investisseur_id')
    ->where('e.type_ecriture', 'ajustement')
    ->where('e.observations', 'like', 'Correction reprise du fichier Excel%')
    ->whereRaw('ABS(e.montant) >= 1')
    ->orderBy('i.identifiant_externe')
    ->orderBy('e.date_ecriture')
    ->get(['e.id', 'e.date_ecriture', 'e.montant', 'i.identifiant_externe', 'i.nom', 'i.prenom']);

echo 'Écritures à requalifier : ' . $aRenommer->count() . "\n";

$total = 0.0;

foreach ($aRenommer as $e) {
    printf("  %-8s %-26s %s  %14s CFA\n", $e->identifiant_externe,
        mb_substr(trim($e->nom . ' ' . $e->prenom), 0, 26),
        substr((string) $e->date_ecriture, 0, 10),
        number_format($e->montant, 2, ',', ' '));
    $total += (float) $e->montant;
}

echo '  total : ' . number_format($total, 2, ',', ' ') . " CFA\n\n";

if ($aRenommer->isEmpty()) {
    exit("Rien à faire.\n");
}

echo 'Les requalifier en « don sortant » ? [oui/non] : ';

if (! in_array(strtolower(trim((string) fgets(STDIN))), ['oui', 'o', 'yes', 'y'], true)) {
    exit("Annulé.\n");
}

DB::transaction(function () use ($aRenommer) {
    foreach ($aRenommer as $e) {
        // La période est celle du mois de l'écriture : c'est ainsi que le classeur
        // rattache le don, et l'observation l'affiche en toutes lettres.
        $periode = substr((string) $e->date_ecriture, 0, 7) . '-01';
        $parametres = ['periode' => $periode];

        DB::table('ecritures_compte_financier')->where('id', $e->id)->update([
            'type_ecriture' => 'don_sortant',
            'observations' => Observation::francais(Observation::DON_FONCTIONNEMENT_WAQF, $parametres),
            'observation_cle' => Observation::DON_FONCTIONNEMENT_WAQF,
            'observation_parametres' => json_encode($parametres, JSON_UNESCAPED_UNICODE),
        ]);
    }
});

AuditLog::enregistrer(
    action: 'correction',
    entite: 'ecriture',
    apres: [
        'operation' => 'sorties requalifiées en dons au fonctionnement du Waqf',
        'nombre' => $aRenommer->count(),
        'montant' => round($total, 2),
        'source' => 'colonne « DON AU WAQF » de la feuille ACTIONNAIRES',
    ],
);

echo "\n" . $aRenommer->count() . ' écriture(s) requalifiée(s), ' . number_format(abs($total), 2, ',', ' ') . " CFA.\n";
