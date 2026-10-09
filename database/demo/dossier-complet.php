<?php

/**
 * Ajoute au jeu de démonstration un dossier qui montre tout.
 *
 * Les dix scripts vidéo demandent, selon le sujet, un compte commercial, un
 * compte waqf, un complément, un paiement, une radiation et un don. Aucun
 * dossier du jeu de démonstration ne réunit les six, et aucun de la base de
 * travail non plus : filmer obligeait donc à changer de dossier au milieu
 * d'une vidéo, ou à sauter un geste.
 *
 * Les événements sont joués à leur date en remontant l'horloge, et passés aux
 * méthodes du domaine — ajouterEcriture(), compteOuCree() — comme le fait
 * PresentationSeeder. Les soldes et les horodatages sont donc ceux qu'aurait
 * produits un usage réel, et non des valeurs posées à la main qui finiraient
 * par ne plus concorder.
 *
 * Le script refuse toute base dont le nom ne contient pas « demo » : il écrit
 * des données fabriquées, et elles n'ont rien à faire dans la base qui porte
 * les vrais dossiers.
 *
 * Usage : php database/demo/dossier-complet.php [nom-de-la-base]
 */

require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\AchatAction;
use App\Models\Don;
use App\Models\Gestionnaire;
use App\Models\Investisseur;
use App\Models\Radiation;
use App\Models\User;
use App\Support\Observation;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

$cible = $argv[1] ?? 'amanah_demo';

if (! str_contains($cible, 'demo')) {
    echo "ABANDON : « {$cible} » n'est pas une base de démonstration.\n";
    echo "Ce script fabrique des données ; elles n'ont rien à faire ailleurs.\n";
    exit(1);
}

config(['database.connections.mysql.database' => $cible]);
DB::purge('mysql');

echo "Base : {$cible}\n\n";

$administrateur = User::where('role', 'administrateur')->orderBy('id')->first();

if (! $administrateur) {
    echo "ABANDON : aucune administrateur dans cette base. Lancez d'abord preparer.php.\n";
    exit(1);
}

Auth::login($administrateur);

$identifiant = 'A0500';
$prix = 25000;

// Le script se rejoue : on refait le dossier plutôt que de renoncer, sinon la
// moindre correction obligerait à reconstruire toute la base.
$existant = Investisseur::where('identifiant_externe', $identifiant)->first();

if ($existant) {
    echo "Le dossier {$identifiant} existait : il est refait.\n\n";

    DB::transaction(function () use ($existant) {
        foreach ($existant->comptes as $compte) {
            Don::where('compte_source_id', $compte->id)
                ->orWhere('compte_destinataire_id', $compte->id)
                ->delete();
            $compte->ecritures()->delete();
            $compte->achats()->delete();
            $compte->radiations()->delete();
            $compte->delete();
        }

        $connexion = $existant->user_id;
        $existant->update(['user_id' => null]);
        $existant->delete();

        if ($connexion) {
            User::whereKey($connexion)->delete();
        }
    });
}

DB::transaction(function () use ($identifiant, $prix, $administrateur) {
    $gestionnaire = Gestionnaire::orderBy('id')->firstOrFail();

    $investisseur = Investisseur::create([
        'identifiant_externe' => $identifiant,
        'type_personne' => 'physique',
        'nom' => 'CAMARA',
        'prenom' => 'Mamadou Mekan',
        'statut' => 'actif',
        'telephone' => '+221770000500',
        'whatsapp' => '+221770000500',
        'ville' => 'Dakar',
        'pays' => 'Sénégal',
        'nationalite' => 'Sénégalaise',
        'gestionnaire_id' => $gestionnaire->id,
        'date_inscription' => '2025-11-10',
    ]);

    $commercial = $investisseur->compteOuCree('commercial');
    $waqf = $investisseur->compteOuCree('waqf');

    /** Un achat, joué à sa date. */
    $acheter = function ($compte, string $date, int $actions, string $type, string $mode) use ($prix, $administrateur) {
        Carbon::setTestNow(Carbon::parse($date . ' 10:00:00'));

        $achat = AchatAction::create([
            'compte_id' => $compte->id,
            'numero_achat' => 'ACH-' . str_pad((string) (AchatAction::max('id') + 1), 4, '0', STR_PAD_LEFT),
            'date_achat' => $date,
            'type_achat' => $type,
            'nombre_actions' => $actions,
            'prix_unitaire' => $prix,
            'montant' => $actions * $prix,
            'mode_paiement' => $mode,
            'saisi_par' => $administrateur->id,
        ]);

        Carbon::setTestNow();

        return $achat;
    };

    // --- 1 et 2. Les deux catégories de compte -----------------------------
    $acheter($commercial, '2025-11-10', 20, 'initial', 'virement');
    $acheter($commercial, '2026-02-18', 8, 'rajout', 'wave');
    $acheter($waqf, '2026-01-20', 4, 'initial', 'especes');

    // --- 3. Un complément : de l'argent versé sans acheter tout de suite ----
    Carbon::setTestNow(Carbon::parse('2026-03-05 11:00:00'));
    $commercial->ajouterEcriture(
        type: 'versement_complementaire',
        montant: 150000,
        dateEcriture: '2026-03-05',
        observations: "Complément versé par l'investisseur, en attente d'achat",
        userId: $administrateur->id,
    );
    Carbon::setTestNow();

    // --- 4. Une radiation partielle, puis son versement ---------------------
    Carbon::setTestNow(Carbon::parse('2026-06-15 09:00:00'));

    $actionsRadiees = 6;
    $montantRadie = $actionsRadiees * $prix;

    $radiation = Radiation::create([
        'compte_id' => $commercial->id,
        'numero_radiation' => 'RAD-' . str_pad((string) (Radiation::max('id') + 1), 4, '0', STR_PAD_LEFT),
        'date_radiation' => '2026-06-15',
        'nombre_actions_radiees' => $actionsRadiees,
        'prix_unitaire_action' => $prix,
        'montant_total' => $montantRadie,
        'mois_previsionnel_paiement' => '2026-07-01',
        'observations' => "Retrait partiel à la demande de l'investisseur",
    ]);

    $commercial->ajouterEcriture(
        type: 'radiation',
        montant: $montantRadie,
        dateEcriture: '2026-06-15',
        referenceType: 'radiations',
        referenceId: $radiation->id,
        observationCle: Observation::RADIATION_CAPITAL,
        observationParametres: ['numero' => $radiation->numero_radiation, 'actions' => $actionsRadiees],
        userId: $administrateur->id,
    );

    Carbon::setTestNow();

    // --- 5. Le paiement : radier n'est pas payer, les deux temps sont là ----
    Carbon::setTestNow(Carbon::parse('2026-07-03 15:30:00'));
    $commercial->ajouterEcriture(
        type: 'paiement',
        montant: -$montantRadie,
        dateEcriture: '2026-07-03',
        observations: 'Versement du capital radié — Wave',
        userId: $administrateur->id,
    );
    Carbon::setTestNow();

    // --- 6. Un don d'actions vers un autre dossier -------------------------
    $beneficiaire = Investisseur::where('id', '!=', $investisseur->id)
        ->whereHas('comptes', fn ($q) => $q->where('categorie', 'commercial'))
        ->orderBy('id')
        ->firstOrFail();

    Carbon::setTestNow(Carbon::parse('2026-08-22 14:00:00'));

    Don::create([
        'compte_source_id' => $commercial->id,
        'compte_destinataire_id' => $beneficiaire->compteOuCree('commercial')->id,
        'type_don' => 'actions',
        'nombre_actions' => 3,
        'prix_unitaire_action' => $prix,
        'date_don' => '2026-08-22',
        'motif' => 'Don familial',
        'created_by' => $administrateur->id,
    ]);

    // Le don de solde, l'autre forme : lui écrit deux écritures, une de chaque
    // côté, là où le don d'actions ne laisse que sa propre ligne.
    Carbon::setTestNow(Carbon::parse('2026-09-12 10:00:00'));

    $compteBeneficiaire = $beneficiaire->compteOuCree('commercial');

    $donDeSolde = Don::create([
        'compte_source_id' => $commercial->id,
        'compte_destinataire_id' => $compteBeneficiaire->id,
        'type_don' => 'solde',
        'montant' => 50000,
        'date_don' => '2026-09-12',
        'motif' => 'Participation aux frais de scolarité',
        'created_by' => $administrateur->id,
    ]);

    $commercial->ajouterEcriture(
        type: 'don_sortant',
        montant: -50000,
        dateEcriture: '2026-09-12',
        referenceType: 'dons',
        referenceId: $donDeSolde->id,
        observationCle: Observation::DON_SORTANT,
        observationParametres: ['beneficiaire' => $beneficiaire->nom, 'motif' => $donDeSolde->motif],
        userId: $administrateur->id,
    );

    $compteBeneficiaire->ajouterEcriture(
        type: 'don_entrant',
        montant: 50000,
        dateEcriture: '2026-09-12',
        referenceType: 'dons',
        referenceId: $donDeSolde->id,
        observationCle: Observation::DON_ENTRANT,
        observationParametres: ['donateur' => 'CAMARA', 'motif' => $donDeSolde->motif],
        userId: $administrateur->id,
    );

    Carbon::setTestNow();

    // Un accès au portail, pour filmer « Mon espace » sur ce même dossier.
    $compteConnexion = User::create([
        'nom' => $investisseur->nom,
        'prenom' => $investisseur->prenom,
        'telephone' => $investisseur->telephone,
        'password' => Hash::make('Amanah2026!'),
        'role' => 'investisseur',
        'langue' => 'fr',
        'actif' => true,
        'doit_changer_mot_de_passe' => false,
    ]);

    $investisseur->update(['user_id' => $compteConnexion->id]);
});

// --- Ce que le dossier montre, une fois posé -------------------------------
$investisseur = Investisseur::where('identifiant_externe', $identifiant)->with('comptes')->firstOrFail();

printf("Dossier %s — %s %s\n\n", $identifiant, $investisseur->nom, $investisseur->prenom);

foreach ($investisseur->comptes as $compte) {
    printf(
        "  %-11s %-14s %3d action(s)   solde %10s CFA\n",
        $compte->categorie,
        $compte->numero_compte,
        $compte->nombreActions(),
        number_format($compte->solde(), 0, ',', ' '),
    );

    foreach ($compte->ecritures()->selectRaw('type_ecriture, count(*) n')->groupBy('type_ecriture')->get() as $e) {
        printf("      %-26s %2d\n", $e->type_ecriture, $e->n);
    }

    foreach ([['achats', $compte->achats()->count()], ['radiations', $compte->radiations()->count()],
              ['dons émis', $compte->donsEmis()->count()]] as [$libelle, $n]) {
        if ($n > 0) {
            printf("      %-26s %2d\n", $libelle, $n);
        }
    }
}

echo "\nAccès portail : " . $investisseur->telephone . " / Amanah2026!\n";
