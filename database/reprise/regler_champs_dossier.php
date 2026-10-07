<?php

/**
 * Remet le signalement « dossier incomplet » en état de signaler.
 *
 * Après la reprise du classeur Excel, les 424 dossiers étaient tous incomplets :
 * six pièces manquaient à la quasi-totalité d'entre eux, parce que le classeur
 * ne les portait pas. L'alerte de la fiche s'affichait donc partout avec la même
 * liste, et le filtre « dossiers incomplets » de la liste sélectionnait tout le
 * monde. Un signal qui se déclenche dans 100 % des cas n'est plus un signal.
 *
 *   424 / 424  Adresse
 *   424 / 424  Convention d'engagement signée
 *   423 / 424  Pièce d'identité scannée
 *   423 / 424  Date de naissance
 *   423 / 424  Lieu de naissance
 *   423 / 424  Nationalité
 *
 * Ces six pièces cessent d'être réclamées, le temps que leur collecte commence
 * pour de bon. Elles restent au catalogue : il suffit de les recocher dans
 * Paramétrage → champs du dossier, une par une, au rythme où on se met à les
 * demander — chaque case recochée fera alors remonter exactement les dossiers
 * à qui elle manque.
 *
 * Ce que la plateforme continue de réclamer : le type et le numéro de pièce
 * d'identité, le pays de résidence ; pour une personne morale, le RCCM et le
 * NINEA. Ce sont les seules lignes que le classeur portait effectivement, et
 * les rares dossiers qui s'en écartent méritent qu'on les voie.
 *
 * Rien n'est supprimé, rien n'est recalculé : seules des cases changent d'état.
 * Pour revenir en arrière, recocher les mêmes lignes à l'écran.
 *
 * Usage : php database/reprise/regler_champs_dossier.php
 */

require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\AuditLog;
use App\Models\ChampDossier;
use App\Models\Investisseur;
use App\Models\User;
use App\Support\Completude;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

Auth::login(User::where('role', 'administrateur')->orderBy('id')->firstOrFail());

/** Ce que le classeur ne portait pas, par contexte. */
$aDecocher = [
    'physique' => [
        'piece_identite_path',
        'date_naissance',
        'lieu_naissance',
        'nationalite',
        'adresse',
        'convention_engagement_path',
    ],
    'morale' => [
        'adresse',
        'convention_engagement_path',
    ],
];

$avant = Completude::filtrerIncomplets(Investisseur::query())->count();
$total = Investisseur::count();

echo "Avant : {$avant} dossier(s) incomplet(s) sur {$total}\n\n";

$modifications = [];

DB::transaction(function () use ($aDecocher, &$modifications) {
    foreach ($aDecocher as $contexte => $champs) {
        foreach ($champs as $champ) {
            $ligne = ChampDossier::firstOrNew(['contexte' => $contexte, 'champ' => $champ]);

            if ($ligne->exists && $ligne->actif === false) {
                echo "  déjà ignoré : {$contexte} / {$champ}\n";

                continue;
            }

            $ligne->actif = false;
            $ligne->save();

            // Même formulation que l'écran de paramétrage, pour que le journal
            // d'audit se lise pareil quelle que soit la main qui a agi.
            $modifications[] = sprintf('%s / %s : %s', $contexte, $champ, 'ignoré');
            echo "  décoché : {$contexte} / {$champ}\n";
        }
    }
});

Completude::oublier();

$apres = Completude::filtrerIncomplets(Investisseur::query())->count();

if ($modifications !== []) {
    AuditLog::enregistrer(
        action: 'modification_champs_dossier',
        entite: 'champ_dossier',
        apres: [
            'modifications' => $modifications,
            'motif' => 'reprise Excel — pièces jamais portées par le classeur, remises au catalogue',
            'incomplets_avant' => $avant,
            'incomplets_apres' => $apres,
        ],
    );
}

echo "\nAprès : {$apres} dossier(s) incomplet(s) sur {$total}\n";

if ($apres > 0) {
    echo "\nCe qui manque encore :\n";

    foreach (Investisseur::with('heritiers')->get() as $investisseur) {
        $manquants = Completude::manquants($investisseur);

        if ($manquants === []) {
            continue;
        }

        printf(
            "  %-8s %-38s %s\n",
            $investisseur->identifiant_externe,
            mb_substr(trim($investisseur->nom . ' ' . $investisseur->prenom), 0, 38),
            implode(' · ', array_map(fn (string $m) => __($m), $manquants)),
        );
    }
}
