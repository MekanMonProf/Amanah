<?php

/**
 * Affecte les derniers dossiers repris, restés sans gestionnaire, à un gestionnaire
 * existant. Passe par Investisseur::transfererVers() — la méthode qu'emploient la
 * fiche investisseur et la réassignation en masse — pour que l'affectation laisse
 * une trace dans historique_affectations.
 *
 * Usage : php storage/app/private/reprise/affecter_gestionnaire.php <id_gestionnaire>
 */

require __DIR__ . '/../../vendor/autoload.php';

$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\AuditLog;
use App\Models\Gestionnaire;
use App\Models\Investisseur;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

$gestionnaireId = (int) ($argv[1] ?? 0);

if ($gestionnaireId <= 0) {
    echo "Gestionnaires disponibles :\n";
    foreach (Gestionnaire::with('user')->get() as $g) {
        echo '  ' . $g->id . ' — ' . $g->user->nom . ' ' . $g->user->prenom
            . ' (' . Investisseur::where('gestionnaire_id', $g->id)->count() . " investisseurs)\n";
    }
    exit("\nIndiquez l'identifiant du gestionnaire en argument.\n");
}

$gestionnaire = Gestionnaire::with('user')->findOrFail($gestionnaireId);
$administrateur = User::where('role', 'administrateur')->orderBy('id')->firstOrFail();
Auth::login($administrateur);

$sansGestionnaire = Investisseur::whereNull('gestionnaire_id')->orderBy('identifiant_externe')->get();

echo 'Base : ' . DB::connection()->getDatabaseName() . "\n";
echo 'Gestionnaire : ' . $gestionnaire->user->nom . ' ' . $gestionnaire->user->prenom . "\n";
echo 'Dossiers sans gestionnaire : ' . $sansGestionnaire->count() . "\n\n";

if ($sansGestionnaire->isEmpty()) {
    exit("Rien à faire.\n");
}

foreach ($sansGestionnaire as $i) {
    echo '  ' . $i->identifiant_externe . '  ' . $i->nom . ' ' . $i->prenom . "\n";
}

echo "\nAffecter ces " . $sansGestionnaire->count() . ' dossier(s) à ' . $gestionnaire->user->nom . ' ' . $gestionnaire->user->prenom . ' ? [oui/non] : ';

if (! in_array(strtolower(trim((string) fgets(STDIN))), ['oui', 'o', 'yes', 'y'], true)) {
    exit("Annulé.\n");
}

DB::transaction(function () use ($sansGestionnaire, $gestionnaire, $administrateur) {
    foreach ($sansGestionnaire as $investisseur) {
        $investisseur->transfererVers(
            $gestionnaire,
            'Affectation initiale — dossiers repris du fichier Excel',
            $administrateur->id
        );
    }
});

AuditLog::enregistrer(
    action: 'reassignation_masse',
    entite: 'gestionnaire',
    entiteId: $gestionnaire->id,
    apres: [
        'gestionnaire' => $gestionnaire->user->nom . ' ' . $gestionnaire->user->prenom,
        'nombre_investisseurs' => $sansGestionnaire->count(),
        'motif' => 'Affectation initiale des dossiers repris du fichier Excel',
    ],
);

echo "\n" . $sansGestionnaire->count() . ' dossier(s) affecté(s). '
    . $gestionnaire->user->nom . ' ' . $gestionnaire->user->prenom . ' en gère désormais '
    . Investisseur::where('gestionnaire_id', $gestionnaire->id)->count() . ".\n";
