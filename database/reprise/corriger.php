<?php

/**
 * Corrections d'après-reprise.
 *
 * À lancer depuis la racine du projet :
 *   php storage/app/private/reprise/corriger.php
 *
 * Deux corrections indépendantes, annoncées puis confirmées une par une :
 *
 *   1. Suppression des comptes ouverts sans aucun contenu. Le rejeu du grand livre
 *      les a créés par erreur : il demandait le compte avant de regarder s'il avait
 *      quelque chose à y écrire. Aucun n'a d'achat, d'écriture, de radiation, de
 *      dividende ni de don — leur suppression ne retire aucune information.
 *
 *   2. Recalage de la date d'inscription des investisseurs sur leur premier achat.
 *      Les dossiers ont été créés en base le 29/09/2026, alors que leurs achats
 *      remontent à 2024 : tout l'historique est donc « antérieur à l'inscription »
 *      pour l'outil de contrôle. Recaler la date rétablit la chronologie réelle.
 */

require __DIR__ . '/../../vendor/autoload.php';

$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$base = DB::connection()->getDatabaseName();

echo "Base : {$base}\n\n";

$demander = function (string $question): bool {
    echo $question . ' [oui/non] : ';
    $reponse = strtolower(trim((string) fgets(STDIN)));

    return in_array($reponse, ['oui', 'o', 'yes', 'y'], true);
};

// ------------------------------------------- 1. Comptes vides
$vides = DB::table('comptes_investissement as c')
    ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('achats_actions')->whereColumn('compte_id', 'c.id'))
    ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('ecritures_compte_financier')->whereColumn('compte_id', 'c.id'))
    ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('radiations')->whereColumn('compte_id', 'c.id'))
    ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('dividendes')->whereColumn('compte_id', 'c.id'))
    ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('dons')->where(fn ($d) => $d->whereColumn('compte_source_id', 'c.id')->orWhereColumn('compte_destinataire_id', 'c.id')))
    ->pluck('c.id');

echo '1. Comptes ouverts sans aucun contenu : ' . $vides->count() . "\n";

if ($vides->isNotEmpty()) {
    $parCategorie = DB::table('comptes_investissement')->whereIn('id', $vides)
        ->select('categorie', DB::raw('COUNT(*) n'))->groupBy('categorie')->pluck('n', 'categorie');

    foreach ($parCategorie as $categorie => $nombre) {
        echo "     {$categorie} : {$nombre}\n";
    }

    if ($demander('   Les supprimer ?')) {
        $supprimes = DB::table('comptes_investissement')->whereIn('id', $vides)->delete();

        \App\Models\AuditLog::enregistrer(
            action: 'nettoyage',
            entite: 'compte',
            apres: ['operation' => 'suppression des comptes ouverts sans contenu par la reprise', 'nombre' => $supprimes],
        );

        echo "   {$supprimes} compte(s) supprimé(s).\n";
    } else {
        echo "   Ignoré.\n";
    }
}

// ------------------------------ 2. Date d'inscription des investisseurs
$aRecaler = DB::select("
    SELECT i.id, i.identifiant_externe, i.created_at, MIN(a.date_achat) AS premier
    FROM investisseurs i
    JOIN comptes_investissement c ON c.investisseur_id = i.id
    JOIN achats_actions a ON a.compte_id = c.id
    GROUP BY i.id, i.identifiant_externe, i.created_at
    HAVING MIN(a.date_achat) < DATE(i.created_at)
");

echo "\n2. Investisseurs dont l'historique précède la date d'inscription en base : " . count($aRecaler) . "\n";

if ($aRecaler !== []) {
    foreach (array_slice($aRecaler, 0, 3) as $i) {
        echo "     {$i->identifiant_externe} : inscrit le " . substr((string) $i->created_at, 0, 10) . ", premier achat le {$i->premier}\n";
    }
    echo '     …' . "\n";

    if ($demander("   Recaler la date d'inscription sur le premier achat ?")) {
        $nombre = 0;

        DB::transaction(function () use ($aRecaler, &$nombre) {
            foreach ($aRecaler as $i) {
                DB::table('investisseurs')->where('id', $i->id)->update(['created_at' => $i->premier . ' 00:00:00']);
                $nombre++;
            }
        });

        \App\Models\AuditLog::enregistrer(
            action: 'nettoyage',
            entite: 'investisseur',
            apres: ['operation' => "date d'inscription recalée sur le premier achat (reprise historique)", 'nombre' => $nombre],
        );

        echo "   {$nombre} dossier(s) recalé(s).\n";
    } else {
        echo "   Ignoré.\n";
    }
}

echo "\nTerminé. Relancez « php artisan amanah:verifier-coherence » pour voir l'effet.\n";
