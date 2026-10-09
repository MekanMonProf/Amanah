<?php

use Illuminate\Support\Facades\Route;
use App\Livewire\TableauDeBord;
use App\Livewire\Investisseurs\InvestisseurIndex;
use App\Livewire\Parametrage\Parametrage;
use App\Livewire\Investisseurs\InvestisseurShow;
use App\Livewire\Investisseurs\InvestisseurEdit;
use App\Livewire\Achats\AchatCreate;
use App\Livewire\Dividendes\DividendeCalcul;
use App\Livewire\Dividendes\BaremeCorrection;
use App\Livewire\Comptes\AjustementCreate;
use App\Livewire\Comptes\ComplementFinancierCreate;
use App\Livewire\Comptes\PaiementCreate;
use App\Livewire\Comptes\AchatSurSolde;
use App\Livewire\Radiations\RadiationCreate;
use App\Livewire\Dons\DonCreate;
use App\Livewire\Successions\DeclarerDeces;
use App\Livewire\Successions\SuccessionIndex;
use App\Livewire\Successions\GererSuccession;
use App\Livewire\Successions\PaiementSuccessionCreate;
use App\Livewire\Gestionnaires\GestionnaireIndex;
use App\Livewire\Gestionnaires\GestionnaireShow;
use App\Livewire\JournalAudit;
use App\Livewire\Auth\ChangerMotDePasseObligatoire;
use App\Livewire\Auth\VerifierDeuxFaCode;
use App\Livewire\Portail\MesDocuments;
use App\Livewire\Portail\MesReleves;
use App\Livewire\Portail\MonCompte;
use App\Http\Controllers\ReleveController;
use App\Http\Controllers\AttestationController;
use App\Http\Controllers\AttestationSuccessionController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\RecuController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\ModeleImportController;
use App\Livewire\Import\ImportIndex;
use App\Livewire\Exports\ExportIndex;
use App\Livewire\Aide\AideIndex;
use App\Livewire\Aide\AidePage;
use App\Livewire\Support\ContacterSupport;

// AMANAH n'a pas de page d'accueil à elle : la présentation est sur la vitrine,
// waqfdolelxamxam.sn. Qui arrive ici vient se connecter.
Route::redirect('/', '/login');

Route::middleware(['auth'])->get('/mot-de-passe/changer-obligatoire', ChangerMotDePasseObligatoire::class)
    ->name('mot-de-passe.changer-obligatoire');

Route::middleware(['auth'])->get('/deux-fa/verifier', VerifierDeuxFaCode::class)
    ->name('deux-fa.verifier');

Route::get('dashboard', TableauDeBord::class)
    ->middleware(['auth', 'verified', 'doit.changer.mdp', 'deux.fa'])
    ->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth', 'doit.changer.mdp', 'deux.fa'])
    ->name('profile');

// Attestations — accessibles à tout utilisateur connecté ; le contrôleur vérifie que
// l'investisseur ne consulte que ses propres documents.
Route::middleware(['auth', 'doit.changer.mdp', 'deux.fa'])->group(function () {
    Route::get('/achats/{achat}/attestation', [AttestationController::class, 'achat'])->name('achats.attestation');
    Route::get('/radiations/{radiation}/attestation', [AttestationController::class, 'radiation'])->name('radiations.attestation');
    Route::get('/ecritures/{ecriture}/attestation', [AttestationController::class, 'paiement'])->name('ecritures.attestation');
    Route::get('/ecritures/{ecriture}/attestation-deces', [AttestationSuccessionController::class, 'versement'])->name('ecritures.attestation.deces');
    Route::get('/ecritures/{ecriture}/recu', [RecuController::class, 'interne'])->name('ecritures.recu');

    // Les pieces jointes ne sont plus servies par le serveur web : elles vivent sur
    // le disque prive et ne sortent que par ici, apres verification des droits.
    Route::get('/documents/{type}/{id}/{colonne}', [DocumentController::class, 'ouvrir'])
        ->whereNumber('id')
        ->name('documents.ouvrir');
});

// Le recu ouvert depuis le lien envoye sur WhatsApp : pas d'authentification, mais
// une signature que le middleware verifie et une expiration reglee dans
// config/societe.php. La plupart des investisseurs n'ont pas de compte ; leur en
// demander un pour lire un recu reviendrait a ne pas le leur envoyer.
Route::get('/recu/{ecriture}', [RecuController::class, 'public'])
    ->middleware('signed')
    ->name('recu.public');

// Portail investisseur (libre-service)
Route::middleware(['auth', 'doit.changer.mdp', 'deux.fa'])->group(function () {
    Route::get('/mon-compte', MonCompte::class)->name('portail.mon-compte');
    Route::get('/mon-compte/releve', [ReleveController::class, 'pourInvestisseur'])->name('portail.releve');
    // La liste des relevés mensuels. Le PDF, lui, reste la route ci-dessus :
    // chaque ligne de la liste n'est qu'un couple de dates passé à celle-ci.
    Route::get('/mon-compte/releves', MesReleves::class)->name('portail.releves.index');
    // Les attestations et reçus. Leurs PDF restent sur leurs propres routes,
    // partagées avec le personnel : chaque contrôleur y vérifie les droits.
    Route::get('/mon-compte/documents', MesDocuments::class)->name('portail.documents.index');
});

// Aide et support : ouverts a tout compte connecte, investisseur compris. Ils
// ne passent par aucun module — refuser le mode d'emploi a quelqu'un qui peut
// ouvrir l'ecran n'aurait pas de sens, et le filtrage par role se fait page
// par page dans App\Support\Aide.
Route::middleware(['auth', 'doit.changer.mdp', 'deux.fa'])->group(function () {
    Route::get('/aide', AideIndex::class)->name('aide.index');
    Route::get('/aide/{sujet}', AidePage::class)->name('aide.sujet');
    Route::get('/support', ContacterSupport::class)->name('support.contacter');
});

Route::middleware(['auth', 'doit.changer.mdp', 'deux.fa'])->group(function () {

    // ---- Consultation des dossiers ----
    // Les listes de roles ont laisse place aux modules : qui accede a quoi se
    // regle desormais depuis Parametrage, sans toucher a ce fichier.
    Route::middleware(['module:investisseurs'])->group(function () {
        Route::get('/investisseurs', InvestisseurIndex::class)->name('investisseurs.index');
        Route::get('/investisseurs/{investisseur}', InvestisseurShow::class)->name('investisseurs.show');
        Route::get('/investisseurs/{investisseur}/releve', [ReleveController::class, 'pourGestionnaire'])->name('investisseurs.releve');
    });

    // ---- Exports ----
    Route::middleware(['module:exports'])->group(function () {
        // Point d'entree unique vers les exports globaux listes juste apres
        Route::get('/exports', ExportIndex::class)->name('exports.index');

        Route::get('/investisseurs/export/csv', [ExportController::class, 'investisseursCsv'])->name('export.investisseurs.csv');
        Route::get('/investisseurs/export/pdf', [ExportController::class, 'investisseursPdf'])->name('export.investisseurs.pdf');
        Route::get('/exports/achats/csv', [ExportController::class, 'achatsGlobalCsv'])->name('export.achats.global.csv');
        Route::get('/exports/achats/pdf', [ExportController::class, 'achatsGlobalPdf'])->name('export.achats.global.pdf');
        Route::get('/exports/ecritures/csv', [ExportController::class, 'ecrituresGlobalCsv'])->name('export.ecritures.global.csv');
        Route::get('/exports/ecritures/pdf', [ExportController::class, 'ecrituresGlobalPdf'])->name('export.ecritures.global.pdf');
        Route::get('/exports/radiations/csv', [ExportController::class, 'radiationsGlobalCsv'])->name('export.radiations.global.csv');
        Route::get('/exports/radiations/pdf', [ExportController::class, 'radiationsGlobalPdf'])->name('export.radiations.global.pdf');
        Route::get('/comptes/{compte}/achats/export/csv', [ExportController::class, 'achatsCsv'])->name('export.achats.csv');
        Route::get('/comptes/{compte}/achats/export/pdf', [ExportController::class, 'achatsPdf'])->name('export.achats.pdf');
        Route::get('/comptes/{compte}/ecritures/export/csv', [ExportController::class, 'ecrituresCsv'])->name('export.ecritures.csv');
        Route::get('/comptes/{compte}/ecritures/export/pdf', [ExportController::class, 'ecrituresPdf'])->name('export.ecritures.pdf');
        Route::get('/comptes/{compte}/radiations/export/csv', [ExportController::class, 'radiationsCsv'])->name('export.radiations.csv');
        Route::get('/comptes/{compte}/radiations/export/pdf', [ExportController::class, 'radiationsPdf'])->name('export.radiations.pdf');
        Route::get('/comptes/{compte}/dons/export/csv', [ExportController::class, 'donsCsv'])->name('export.dons.csv');
        Route::get('/comptes/{compte}/dons/export/pdf', [ExportController::class, 'donsPdf'])->name('export.dons.pdf');
    });

    // ---- Operations sur les dossiers ----
    Route::middleware(['module:investisseurs,ecriture'])->group(function () {
        Route::get('/investisseurs/{investisseur}/modifier', InvestisseurEdit::class)->name('investisseurs.modifier');
        Route::get('/investisseurs/{investisseur}/achats/creer', AchatCreate::class)->name('achats.creer');
        Route::get('/comptes/{compte}/complement', ComplementFinancierCreate::class)->name('comptes.complement');
        Route::get('/comptes/{compte}/paiement', PaiementCreate::class)->name('comptes.paiement');
        Route::get('/comptes/{compte}/achat-sur-solde', AchatSurSolde::class)->name('comptes.achat-sur-solde');
        Route::get('/comptes/{compte}/radiations/creer', RadiationCreate::class)->name('radiations.creer');
        Route::get('/comptes/{compte}/dons/creer', DonCreate::class)->name('dons.creer');
    });

    // ---- Dividendes ----
    // L'ajustement manuel d'un compte tient du pilotage financier, pas de la
    // tenue de dossier : il suit les baremes plutot que les investisseurs.
    Route::middleware(['module:dividendes,ecriture'])->group(function () {
        Route::get('/dividendes/calculer', DividendeCalcul::class)->name('dividendes.calculer');
        Route::get('/baremes/{bareme}/corriger', BaremeCorrection::class)->name('baremes.corriger');
        Route::get('/comptes/{compte}/ajustement', AjustementCreate::class)->name('comptes.ajustement');
    });

    // ---- Gestionnaires ----
    Route::middleware(['module:gestionnaires,ecriture'])->group(function () {
        Route::get('/gestionnaires', GestionnaireIndex::class)->name('gestionnaires.index');
        // La fiche porte les mêmes actions que la liste : même module, même
        // exigence d'écriture.
        Route::get('/gestionnaires/{gestionnaire}', GestionnaireShow::class)->name('gestionnaires.show');
    });

    // ---- Journal d'audit ----
    Route::middleware(['module:audit'])->group(function () {
        Route::get('/audit', JournalAudit::class)->name('audit.index');
        Route::get('/audit/export/csv', [ExportController::class, 'auditCsv'])->name('export.audit.csv');
        Route::get('/audit/export/pdf', [ExportController::class, 'auditPdf'])->name('export.audit.pdf');
    });

    // ---- Import ----
    // Reprise de l'existant depuis un fichier Excel/CSV — création en masse
    Route::middleware(['module:import,ecriture'])->group(function () {
        Route::get('/import', ImportIndex::class)->name('import.index');
        Route::get('/import/modele/{type}', [ModeleImportController::class, 'telecharger'])->name('import.modele');
    });

    // ---- Successions ----
    // Décès, héritiers, liquidation, versement au mandataire
    Route::middleware(['module:successions,ecriture'])->group(function () {
        Route::get('/successions', SuccessionIndex::class)->name('successions.index');
        Route::get('/investisseurs/{investisseur}/deces/declarer', DeclarerDeces::class)->name('deces.declarer');
        Route::get('/investisseurs/{investisseur}/succession', GererSuccession::class)->name('successions.gerer');
        Route::get('/comptes/{compte}/paiement-succession', PaiementSuccessionCreate::class)->name('successions.paiement');
    });

    // ---- Paramétrage ----
    Route::middleware(['module:parametrage,ecriture'])->group(function () {
        Route::get('/parametrage', Parametrage::class)->name('parametrage.index');
    });
});

require __DIR__.'/auth.php';
