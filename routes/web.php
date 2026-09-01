<?php

use Illuminate\Support\Facades\Route;
use App\Livewire\TableauDeBord;
use App\Livewire\Investisseurs\InvestisseurIndex;
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
use App\Livewire\Successions\GererSuccession;
use App\Livewire\Successions\PaiementSuccessionCreate;
use App\Livewire\Gestionnaires\GestionnaireIndex;
use App\Livewire\JournalAudit;
use App\Livewire\Auth\ChangerMotDePasseObligatoire;
use App\Livewire\Auth\VerifierDeuxFaCode;
use App\Livewire\Portail\MonCompte;
use App\Http\Controllers\ReleveController;
use App\Http\Controllers\AttestationController;
use App\Http\Controllers\AttestationSuccessionController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\ModeleImportController;
use App\Livewire\Import\ImportIndex;

Route::view('/', 'welcome');

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
});

// Portail investisseur (libre-service)
Route::middleware(['auth', 'doit.changer.mdp', 'deux.fa'])->group(function () {
    Route::get('/mon-compte', MonCompte::class)->name('portail.mon-compte');
    Route::get('/mon-compte/releve', [ReleveController::class, 'pourInvestisseur'])->name('portail.releve');
});

Route::middleware(['auth', 'doit.changer.mdp', 'deux.fa'])->group(function () {

    // ---- Niveau 1 : CONSULTATION (Direction, Administrateur, Gestionnaire, Lecture) ----
    Route::middleware(['role:direction,administrateur,gestionnaire,lecture'])->group(function () {
        Route::get('/investisseurs', InvestisseurIndex::class)->name('investisseurs.index');
        Route::get('/investisseurs/{investisseur}', InvestisseurShow::class)->name('investisseurs.show');
        Route::get('/investisseurs/{investisseur}/releve', [ReleveController::class, 'pourGestionnaire'])->name('investisseurs.releve');

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
    });

    // ---- Niveau 2 : OPÉRATIONS COURANTES (Direction, Administrateur, Gestionnaire) ----
    Route::middleware(['role:direction,administrateur,gestionnaire'])->group(function () {
        Route::get('/investisseurs/{investisseur}/modifier', InvestisseurEdit::class)->name('investisseurs.modifier');
        Route::get('/investisseurs/{investisseur}/achats/creer', AchatCreate::class)->name('achats.creer');
        Route::get('/comptes/{compte}/complement', ComplementFinancierCreate::class)->name('comptes.complement');
        Route::get('/comptes/{compte}/paiement', PaiementCreate::class)->name('comptes.paiement');
        Route::get('/comptes/{compte}/achat-sur-solde', AchatSurSolde::class)->name('comptes.achat-sur-solde');
        Route::get('/comptes/{compte}/radiations/creer', RadiationCreate::class)->name('radiations.creer');
        Route::get('/comptes/{compte}/dons/creer', DonCreate::class)->name('dons.creer');
    });

    // ---- Niveau 3 : ADMINISTRATION (Direction, Administrateur uniquement) ----
    Route::middleware(['role:direction,administrateur'])->group(function () {
        Route::get('/dividendes/calculer', DividendeCalcul::class)->name('dividendes.calculer');
        Route::get('/baremes/{bareme}/corriger', BaremeCorrection::class)->name('baremes.corriger');
        Route::get('/comptes/{compte}/ajustement', AjustementCreate::class)->name('comptes.ajustement');
        Route::get('/gestionnaires', GestionnaireIndex::class)->name('gestionnaires.index');
        Route::get('/audit', JournalAudit::class)->name('audit.index');
        Route::get('/audit/export/csv', [ExportController::class, 'auditCsv'])->name('export.audit.csv');
        Route::get('/audit/export/pdf', [ExportController::class, 'auditPdf'])->name('export.audit.pdf');

        // Reprise de l'existant depuis un fichier Excel/CSV — création en masse, donc réservée
        Route::get('/import', ImportIndex::class)->name('import.index');
        Route::get('/import/modele/{type}', [ModeleImportController::class, 'telecharger'])->name('import.modele');

        // Succession (décès, héritiers, répartition) — action sensible, réservée
        Route::get('/investisseurs/{investisseur}/deces/declarer', DeclarerDeces::class)->name('deces.declarer');
        Route::get('/investisseurs/{investisseur}/succession', GererSuccession::class)->name('successions.gerer');
        Route::get('/comptes/{compte}/paiement-succession', PaiementSuccessionCreate::class)->name('successions.paiement');
    });
});

require __DIR__.'/auth.php';
