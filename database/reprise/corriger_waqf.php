<?php

/**
 * Remet le compte caritatif du Waqf d'aplomb, après la reprise.
 *
 * À lancer depuis la racine du projet :
 *   php storage/app/private/reprise/corriger_waqf.php
 *
 * Trois opérations, confirmées une par une :
 *
 *   1. Suppression du dossier créé par erreur lors d'une inspection. Investisseur::
 *      waqfCaritatif() est un firstOrCreate : l'appeler pour savoir ce qu'il trouve
 *      suffit à créer ce qu'il ne trouvait pas.
 *
 *   2. Alignement du dossier A0107 sur ce que le code attend du compte caritatif :
 *      nom exactement égal à Investisseur::NOM_WAQF_CARITATIF, personne morale.
 *      Sans cela, le règlement d'une succession waqf et les achats offerts à la
 *      mémoire d'un défunt créeraient un second compte caritatif vide et y
 *      enverraient le capital, laissant le vrai de côté.
 *
 *   3. Suppression du compte commercial du Waqf. Il ne porte aucune action : les
 *      actions que le classeur comptait de ce côté sont des actions waqf. Ses neuf
 *      écritures sont des dividendes versés sur une action qui n'a jamais existé.
 */

require __DIR__ . '/../../vendor/autoload.php';

$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\AuditLog;
use App\Models\Investisseur;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

Auth::login(User::where('role', 'administrateur')->orderBy('id')->firstOrFail());

echo 'Base : ' . DB::connection()->getDatabaseName() . "\n\n";

$demander = function (string $question): bool {
    echo $question . ' [oui/non] : ';

    return in_array(strtolower(trim((string) fgets(STDIN))), ['oui', 'o', 'yes', 'y'], true);
};

$NOM = Investisseur::NOM_WAQF_CARITATIF;

// ----------------------------------------- 1. Le dossier créé par erreur
$doublons = Investisseur::where('nom', $NOM)
    ->where('identifiant_externe', '!=', 'A0107')
    ->get()
    ->filter(fn ($i) => $i->comptes()->count() === 0 && $i->user_id === null);

echo "1. Dossiers « {$NOM} » vides, créés par inspection : " . $doublons->count() . "\n";

foreach ($doublons as $i) {
    echo "     {$i->identifiant_externe} — créé le " . $i->created_at->format('d/m/Y à H:i') . "\n";
}

if ($doublons->isNotEmpty() && $demander('   Les supprimer ?')) {
    $identifiants = $doublons->pluck('identifiant_externe')->all();
    Investisseur::whereIn('id', $doublons->pluck('id'))->delete();

    AuditLog::enregistrer(
        action: 'nettoyage',
        entite: 'investisseur',
        apres: ['operation' => 'suppression de dossiers caritatifs vides créés par erreur', 'dossiers' => $identifiants],
    );

    echo '   ' . count($identifiants) . " dossier(s) supprimé(s).\n";
}

// ------------------------------------------ 2. Alignement du dossier A0107
$waqf = Investisseur::where('identifiant_externe', 'A0107')->firstOrFail();

echo "\n2. Dossier A0107 : « {$waqf->nom} | {$waqf->prenom} », personne {$waqf->type_personne}\n";
echo "   Attendu par le code : « {$NOM} », personne morale\n";
echo '   Reconnu comme compte caritatif : ' . ($waqf->estWaqfCaritatif() ? 'oui' : 'NON') . "\n";

if (! $waqf->estWaqfCaritatif() && $demander('   L\'aligner ?')) {
    $avant = $waqf->only(['nom', 'prenom', 'type_personne', 'raison_sociale']);

    $waqf->update([
        'nom' => $NOM,
        'prenom' => null,
        'type_personne' => 'morale',
        'raison_sociale' => $NOM,
    ]);

    AuditLog::enregistrer(
        action: 'modification',
        entite: 'investisseur',
        entiteId: $waqf->id,
        avant: $avant,
        apres: $waqf->only(['nom', 'prenom', 'type_personne', 'raison_sociale'])
            + ['motif' => 'alignement sur la constante NOM_WAQF_CARITATIF, pour que les successions waqf trouvent ce compte'],
    );

    echo '   Aligné. Reconnu : ' . ($waqf->fresh()->estWaqfCaritatif() ? 'oui' : 'NON') . "\n";
}

// -------------------------------------- 3. Le compte commercial fantôme
$commercial = $waqf->comptes()->where('categorie', 'commercial')->first();

echo "\n3. Compte commercial du Waqf : " . ($commercial ? $commercial->numero_compte : 'aucun') . "\n";

if ($commercial) {
    $actions = (int) $commercial->achats()->sum('nombre_actions');
    $ecritures = $commercial->ecritures()->count();
    $dividendes = $commercial->dividendes()->count();

    echo "     actions    : {$actions}\n";
    echo "     écritures  : {$ecritures} (solde " . number_format($commercial->solde(), 2, ',', ' ') . " CFA)\n";
    echo "     dividendes : {$dividendes}\n";

    if ($actions > 0) {
        echo "   Ce compte porte des actions : suppression refusée.\n";
    } elseif ($demander('   Le supprimer avec ses écritures et ses dividendes ?')) {
        $numero = $commercial->numero_compte;
        $solde = $commercial->solde();

        DB::transaction(function () use ($commercial) {
            $commercial->dividendes()->delete();
            $commercial->ecritures()->delete();
            $commercial->delete();
        });

        AuditLog::enregistrer(
            action: 'nettoyage',
            entite: 'compte',
            apres: [
                'operation' => 'suppression du compte commercial du Waqf caritatif',
                'compte' => $numero,
                'motif' => "aucune action : les actions que le classeur comptait de ce côté sont des actions waqf",
                'ecritures_supprimees' => $ecritures,
                'dividendes_supprimes' => $dividendes,
                'solde_annule' => $solde,
            ],
        );

        echo "   Supprimé ({$ecritures} écriture(s), {$dividendes} dividende(s), "
            . number_format($solde, 2, ',', ' ') . " CFA annulés).\n";
    }
}

echo "\nTerminé.\n";
