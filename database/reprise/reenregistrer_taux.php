<?php

/**
 * Réenregistre les taux de dividende en précision complète, après l'élargissement
 * de la colonne benefice_par_action (migration du 04/10/2026).
 *
 * À lancer depuis la racine du projet, migration déjà passée :
 *   php storage/app/private/reprise/reenregistrer_taux.php
 *
 * Met à jour les barèmes et les dividendes déjà enregistrés, puis recalcule le
 * montant de chaque dividende à partir du taux complet. Les montants crédités aux
 * comptes ne bougent pas : l'écart, de l'ordre du centime, était déjà absorbé par
 * les écritures d'arrondi de la reprise.
 */

require __DIR__ . '/../../vendor/autoload.php';

$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$taux = json_decode(file_get_contents(__DIR__ . '/taux_precis.json'), true);

echo 'Base : ' . DB::connection()->getDatabaseName() . "\n";

// La migration doit être passée, sinon MySQL tronquerait silencieusement.
$colonne = DB::selectOne("SHOW COLUMNS FROM baremes_dividendes LIKE 'benefice_par_action'");

if (! str_contains(strtolower($colonne->Type ?? ''), '15,8')) {
    exit("ARRÊT : la colonne est encore en {$colonne->Type}. Lancez d'abord « php artisan migrate ».\n");
}

echo 'Taux disponibles : ' . count($taux) . "\n\n";

$baremes = $dividendes = $montants = 0;

DB::transaction(function () use ($taux, &$baremes, &$dividendes, &$montants) {
    foreach ($taux as $mois => $valeur) {
        $periode = $mois . '-01';
        $precis = number_format($valeur, 8, '.', '');

        $baremes += DB::table('baremes_dividendes')
            ->where('periode', $periode)
            ->update(['benefice_par_action' => $precis]);

        $dividendes += DB::table('dividendes')
            ->where('periode', $periode)
            ->update(['benefice_par_action' => $precis]);
    }

    // Le montant doit redevenir le produit exact de ce que la base contient,
    // sinon le contrôle d'arithmétique continuerait de protester.
    $montants = DB::update('
        UPDATE dividendes
        SET montant_calcule = ROUND(nombre_actions * benefice_par_action, 2)
        WHERE ABS(montant_calcule - ROUND(nombre_actions * benefice_par_action, 2)) > 0.001
    ');
});

echo "  barèmes mis à jour    : {$baremes}\n";
echo "  dividendes mis à jour : {$dividendes}\n";
echo "  montants recalculés   : {$montants}\n";

$restants = DB::selectOne('
    SELECT COUNT(*) n FROM dividendes
    WHERE ABS(montant_calcule - nombre_actions * benefice_par_action) > 0.01
')->n;

echo "\n  dividendes dont le montant ne retombe pas sur actions × taux : {$restants}\n";

\App\Models\AuditLog::enregistrer(
    action: 'correction',
    entite: 'bareme_dividende',
    apres: [
        'operation' => 'taux réenregistrés en précision complète après élargissement de la colonne',
        'baremes' => $baremes,
        'dividendes' => $dividendes,
        'montants_recalcules' => $montants,
    ],
);

echo "\nTerminé.\n";
