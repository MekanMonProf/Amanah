<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\CompteInvestissement;
use App\Models\Investisseur;
use App\Support\RestreintAuPortefeuilleGestionnaire;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ExportController extends Controller
{
    /** Excel ouvre un CSV en ANSI sans cette marque, et les accents y tombent. */
    private const BOM = "\xEF\xBB\xBF";

    use \App\Http\Controllers\Concerns\RendPdf;

    use RestreintAuPortefeuilleGestionnaire;

    /**
     * Restreint une requête (jointe à `compte.investisseur`) au portefeuille du
     * gestionnaire connecté — utilisé par les exports globaux, qui ne portent pas
     * de {compte}/{investisseur} sur lequel appliquer assurerAccesGestionnaire().
     */
    protected function limiterAuPortefeuilleGestionnaire($query, string $cheminInvestisseur = 'compte.investisseur'): void
    {
        if (Auth::user()->role === 'gestionnaire') {
            $query->whereHas($cheminInvestisseur, fn ($q) => $q->where('gestionnaire_id', Auth::user()->gestionnaire?->id));
        }
    }

    // --- Achats d'un compte ---------------------------------------------

    protected function achatsFiltres(Request $request, CompteInvestissement $compte)
    {
        $this->assurerAccesGestionnairePourCompte($compte);

        $query = $compte->achats()->getQuery();

        if ($recherche = $request->query('recherche')) {
            $query->where(function ($q) use ($recherche) {
                $q->where('numero_achat', 'like', "%{$recherche}%")
                  ->orWhere('reference_facture', 'like', "%{$recherche}%")
                  ->orWhere('mode_paiement', 'like', "%{$recherche}%");
            });
        }
        if ($type = $request->query('type')) {
            $query->where('type_achat', $type);
        }
        if ($debut = $request->query('date_debut')) {
            $query->whereDate('date_achat', '>=', $debut);
        }
        if ($fin = $request->query('date_fin')) {
            $query->whereDate('date_achat', '<=', $fin);
        }

        return $query->orderBy('date_achat');
    }

    public function achatsCsv(Request $request, CompteInvestissement $compte)
    {
        $achats = $this->achatsFiltres($request, $compte)->get();

        return response()->streamDownload(function () use ($achats) {
            $flux = fopen('php://output', 'w');
            fwrite($flux, self::BOM);
            fputcsv($flux, ['N° achat', 'Date', 'Type', 'Actions', 'Prix unitaire', 'Montant', 'Mode paiement', 'Référence'], ';');
            foreach ($achats as $a) {
                fputcsv($flux, [$a->numero_achat, $a->date_achat->format('d/m/Y'), $a->type_achat, $a->nombre_actions, $a->prix_unitaire, $a->montant, $a->mode_paiement, $a->reference_facture], ';');
            }
            fclose($flux);
        }, 'achats_' . $compte->numero_compte . '_' . now()->format('Y-m-d') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function achatsPdf(Request $request, CompteInvestissement $compte)
    {
        $achats = $this->achatsFiltres($request, $compte)->get();

        $pdf = Pdf::loadView('pdf.liste-achats', [
            'achats' => $achats, 'compte' => $compte->load('investisseur'), 'dateGeneration' => now(),
        ])->setPaper('a4', 'portrait');

        return $this->telechargerPdf($pdf, 'Achats_' . $compte->numero_compte . '_' . now()->format('Y-m-d') . '.pdf');
    }

    // --- Écritures d'un compte -------------------------------------------

    protected function ecrituresFiltres(Request $request, CompteInvestissement $compte)
    {
        $this->assurerAccesGestionnairePourCompte($compte);

        $query = $compte->ecritures()->getQuery()->reorder('id', 'asc');

        if ($recherche = $request->query('recherche')) {
            // Meme regle qu a l ecran : le terme cherche est aussi rapproche des
            // cles d observation, pour que l export rende les memes lignes que
            // la liste, quelle que soit la langue de saisie.
            $cles = \App\Support\Observation::clesCorrespondant($recherche);

            $query->where(function ($q) use ($recherche, $cles) {
                $q->where('observations', 'like', "%{$recherche}%");

                if ($cles !== []) {
                    $q->orWhereIn('observation_cle', $cles);
                }
            });
        }
        if ($type = $request->query('type')) {
            $query->where('type_ecriture', $type);
        }
        if ($debut = $request->query('date_debut')) {
            $query->whereDate('date_ecriture', '>=', $debut);
        }
        if ($fin = $request->query('date_fin')) {
            $query->whereDate('date_ecriture', '<=', $fin);
        }

        return $query;
    }

    public function ecrituresCsv(Request $request, CompteInvestissement $compte)
    {
        $ecritures = $this->ecrituresFiltres($request, $compte)->get();

        return response()->streamDownload(function () use ($ecritures) {
            $flux = fopen('php://output', 'w');
            fwrite($flux, self::BOM);
            fputcsv($flux, ['Date', 'Type', 'Montant', 'Solde après', 'Observations'], ';');
            foreach ($ecritures as $e) {
                fputcsv($flux, [$e->date_ecriture->format('d/m/Y'), $e->type_ecriture, $e->montant, $e->solde_apres, $e->observations], ';');
            }
            fclose($flux);
        }, 'ecritures_' . $compte->numero_compte . '_' . now()->format('Y-m-d') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function ecrituresPdf(Request $request, CompteInvestissement $compte)
    {
        $ecritures = $this->ecrituresFiltres($request, $compte)->get();

        $pdf = Pdf::loadView('pdf.liste-ecritures', [
            'ecritures' => $ecritures, 'compte' => $compte->load('investisseur'), 'dateGeneration' => now(),
        ])->setPaper('a4', 'portrait');

        return $this->telechargerPdf($pdf, 'Ecritures_' . $compte->numero_compte . '_' . now()->format('Y-m-d') . '.pdf');
    }

    // --- Radiations d'un compte -------------------------------------------

    protected function radiationsFiltres(Request $request, CompteInvestissement $compte)
    {
        $this->assurerAccesGestionnairePourCompte($compte);

        $query = $compte->radiations()->getQuery()->reorder();

        if ($recherche = $request->query('recherche')) {
            $query->where(function ($q) use ($recherche) {
                $q->where('numero_radiation', 'like', "%{$recherche}%")
                  ->orWhere('reference_facture', 'like', "%{$recherche}%");
            });
        }
        if ($debut = $request->query('date_debut')) {
            $query->whereDate('date_radiation', '>=', $debut);
        }
        if ($fin = $request->query('date_fin')) {
            $query->whereDate('date_radiation', '<=', $fin);
        }

        return $query->orderBy('date_radiation');
    }

    public function radiationsCsv(Request $request, CompteInvestissement $compte)
    {
        $radiations = $this->radiationsFiltres($request, $compte)->get();

        return response()->streamDownload(function () use ($radiations) {
            $flux = fopen('php://output', 'w');
            fwrite($flux, self::BOM);
            fputcsv($flux, ['N° radiation', 'Date', 'Actions radiées', 'Prix unitaire', 'Montant total', 'Référence'], ';');
            foreach ($radiations as $r) {
                fputcsv($flux, [$r->numero_radiation, $r->date_radiation->format('d/m/Y'), $r->nombre_actions_radiees, $r->prix_unitaire_action, $r->montant_total, $r->reference_facture], ';');
            }
            fclose($flux);
        }, 'radiations_' . $compte->numero_compte . '_' . now()->format('Y-m-d') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function radiationsPdf(Request $request, CompteInvestissement $compte)
    {
        $radiations = $this->radiationsFiltres($request, $compte)->get();

        $pdf = Pdf::loadView('pdf.liste-radiations', [
            'radiations' => $radiations, 'compte' => $compte->load('investisseur'), 'dateGeneration' => now(),
        ])->setPaper('a4', 'portrait');

        return $this->telechargerPdf($pdf, 'Radiations_' . $compte->numero_compte . '_' . now()->format('Y-m-d') . '.pdf');
    }

    /**
     * Les dons d'un compte, dans les deux sens.
     *
     * Ils manquaient aux exports alors que les achats, les radiations et les
     * écritures y étaient tous. Un don de solde laisse bien deux écritures et
     * se retrouvait donc ailleurs — mais un don d'actions n'écrit que sa propre
     * ligne, et c'est pourtant nombreActions() qui la compte. Sans cet export,
     * le nombre d'actions d'un compte ne se réconciliait pas à partir de ses
     * fichiers : il manquait toujours ce qui avait été donné ou reçu.
     */
    protected function donsFiltres(Request $request, CompteInvestissement $compte)
    {
        $this->assurerAccesGestionnairePourCompte($compte);

        $query = \App\Models\Don::with(['compteSource.investisseur', 'compteDestinataire.investisseur']);

        // Le compte est soit la source, soit le destinataire, jamais les deux :
        // DonCreate refuse un don vers le compte d'origine.
        match ($request->query('sens')) {
            'emis' => $query->where('compte_source_id', $compte->id),
            'recus' => $query->where('compte_destinataire_id', $compte->id),
            default => $query->where(function ($q) use ($compte) {
                $q->where('compte_source_id', $compte->id)
                  ->orWhere('compte_destinataire_id', $compte->id);
            }),
        };

        if ($recherche = $request->query('recherche')) {
            $query->where('motif', 'like', "%{$recherche}%");
        }
        if ($debut = $request->query('date_debut')) {
            $query->whereDate('date_don', '>=', $debut);
        }
        if ($fin = $request->query('date_fin')) {
            $query->whereDate('date_don', '<=', $fin);
        }

        return $query->orderBy('date_don');
    }

    /** Le sens du don vu depuis ce compte : il l'a donné, ou il l'a reçu. */
    protected function sensDuDon(\App\Models\Don $don, CompteInvestissement $compte): string
    {
        return $don->compte_source_id === $compte->id ? 'Donné' : 'Reçu';
    }

    public function donsCsv(Request $request, CompteInvestissement $compte)
    {
        $dons = $this->donsFiltres($request, $compte)->get();

        return response()->streamDownload(function () use ($dons, $compte) {
            $flux = fopen('php://output', 'w');
            fwrite($flux, self::BOM);
            fputcsv($flux, ['Date', 'Sens', 'Type', 'Contrepartie', 'Identifiant', 'Actions', 'Montant', 'Motif'], ';');

            foreach ($dons as $d) {
                $sens = $this->sensDuDon($d, $compte);
                $autre = $sens === 'Donné' ? $d->compteDestinataire : $d->compteSource;

                fputcsv($flux, [
                    $d->date_don->format('d/m/Y'),
                    $sens,
                    $d->type_don === 'actions' ? 'Actions' : 'Solde',
                    trim(($autre->investisseur->nom ?? '') . ' ' . ($autre->investisseur->prenom ?? '')),
                    $autre->investisseur->identifiant_externe ?? '',
                    $d->nombre_actions,
                    $d->montant,
                    $d->motif,
                ], ';');
            }

            fclose($flux);
        }, 'dons_' . $compte->numero_compte . '_' . now()->format('Y-m-d') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function donsPdf(Request $request, CompteInvestissement $compte)
    {
        $dons = $this->donsFiltres($request, $compte)->get();

        $pdf = Pdf::loadView('pdf.liste-dons', [
            'dons' => $dons,
            'compte' => $compte->load('investisseur'),
            'dateGeneration' => now(),
        ])->setPaper('a4', 'portrait');

        return $this->telechargerPdf($pdf, 'Dons_' . $compte->numero_compte . '_' . now()->format('Y-m-d') . '.pdf');
    }

    // --- Exports globaux (tous les comptes, tous les investisseurs) -----

    protected function filtresDates(Request $request, $query, string $colonneDate)
    {
        if ($debut = $request->query('date_debut')) {
            $query->whereDate($colonneDate, '>=', $debut);
        }
        if ($fin = $request->query('date_fin')) {
            $query->whereDate($colonneDate, '<=', $fin);
        }

        return $query;
    }

    public function achatsGlobalCsv(Request $request)
    {
        $query = \App\Models\AchatAction::with('compte.investisseur')->orderBy('date_achat');
        $this->limiterAuPortefeuilleGestionnaire($query);
        $achats = $this->filtresDates($request, $query, 'date_achat')->get();

        return response()->streamDownload(function () use ($achats) {
            $flux = fopen('php://output', 'w');
            fwrite($flux, self::BOM);
            fputcsv($flux, ['Investisseur', 'Identifiant', 'Compte', 'Catégorie', 'N° achat', 'Date', 'Type', 'Actions', 'Prix unitaire', 'Montant', 'Mode paiement'], ';');
            foreach ($achats as $a) {
                fputcsv($flux, [
                    $a->compte->investisseur->nom . ' ' . $a->compte->investisseur->prenom,
                    $a->compte->investisseur->identifiant_externe,
                    $a->compte->numero_compte, $a->compte->categorie,
                    $a->numero_achat, $a->date_achat->format('d/m/Y'), $a->type_achat,
                    $a->nombre_actions, $a->prix_unitaire, $a->montant, $a->mode_paiement,
                ], ';');
            }
            fclose($flux);
        }, 'achats_tous_' . now()->format('Y-m-d') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function achatsGlobalPdf(Request $request)
    {
        $query = \App\Models\AchatAction::with('compte.investisseur')->orderBy('date_achat');
        $this->limiterAuPortefeuilleGestionnaire($query);
        $achats = $this->filtresDates($request, $query, 'date_achat')->get();

        $pdf = Pdf::loadView('pdf.liste-achats-global', ['achats' => $achats, 'dateGeneration' => now()])->setPaper('a4', 'landscape');

        return $this->telechargerPdf($pdf, 'Achats_Tous_' . now()->format('Y-m-d') . '.pdf');
    }

    public function ecrituresGlobalCsv(Request $request)
    {
        $query = \App\Models\EcritureCompteFinancier::with('compte.investisseur')->orderBy('id');
        $this->limiterAuPortefeuilleGestionnaire($query);
        $ecritures = $this->filtresDates($request, $query, 'date_ecriture')->get();

        return response()->streamDownload(function () use ($ecritures) {
            $flux = fopen('php://output', 'w');
            fwrite($flux, self::BOM);
            fputcsv($flux, ['Investisseur', 'Identifiant', 'Compte', 'Catégorie', 'Date', 'Type', 'Montant', 'Solde après', 'Observations'], ';');
            foreach ($ecritures as $e) {
                fputcsv($flux, [
                    $e->compte->investisseur->nom . ' ' . $e->compte->investisseur->prenom,
                    $e->compte->investisseur->identifiant_externe,
                    $e->compte->numero_compte, $e->compte->categorie,
                    $e->date_ecriture->format('d/m/Y'), $e->type_ecriture, $e->montant, $e->solde_apres, $e->observations,
                ], ';');
            }
            fclose($flux);
        }, 'ecritures_toutes_' . now()->format('Y-m-d') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function ecrituresGlobalPdf(Request $request)
    {
        $query = \App\Models\EcritureCompteFinancier::with('compte.investisseur')->orderBy('id');
        $this->limiterAuPortefeuilleGestionnaire($query);
        $ecritures = $this->filtresDates($request, $query, 'date_ecriture')->get();

        $pdf = Pdf::loadView('pdf.liste-ecritures-global', ['ecritures' => $ecritures, 'dateGeneration' => now()])->setPaper('a4', 'landscape');

        return $this->telechargerPdf($pdf, 'Ecritures_Toutes_' . now()->format('Y-m-d') . '.pdf');
    }

    public function radiationsGlobalCsv(Request $request)
    {
        $query = \App\Models\Radiation::with('compte.investisseur')->orderBy('date_radiation');
        $this->limiterAuPortefeuilleGestionnaire($query);
        $radiations = $this->filtresDates($request, $query, 'date_radiation')->get();

        return response()->streamDownload(function () use ($radiations) {
            $flux = fopen('php://output', 'w');
            fwrite($flux, self::BOM);
            fputcsv($flux, ['Investisseur', 'Identifiant', 'Compte', 'Catégorie', 'N° radiation', 'Date', 'Actions radiées', 'Prix unitaire', 'Montant total'], ';');
            foreach ($radiations as $r) {
                fputcsv($flux, [
                    $r->compte->investisseur->nom . ' ' . $r->compte->investisseur->prenom,
                    $r->compte->investisseur->identifiant_externe,
                    $r->compte->numero_compte, $r->compte->categorie,
                    $r->numero_radiation, $r->date_radiation->format('d/m/Y'),
                    $r->nombre_actions_radiees, $r->prix_unitaire_action, $r->montant_total,
                ], ';');
            }
            fclose($flux);
        }, 'radiations_toutes_' . now()->format('Y-m-d') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function radiationsGlobalPdf(Request $request)
    {
        $query = \App\Models\Radiation::with('compte.investisseur')->orderBy('date_radiation');
        $this->limiterAuPortefeuilleGestionnaire($query);
        $radiations = $this->filtresDates($request, $query, 'date_radiation')->get();

        $pdf = Pdf::loadView('pdf.liste-radiations-global', ['radiations' => $radiations, 'dateGeneration' => now()])->setPaper('a4', 'landscape');

        return $this->telechargerPdf($pdf, 'Radiations_Toutes_' . now()->format('Y-m-d') . '.pdf');
    }

    // --- Dons (liste globale) --------------------------------------------

    /**
     * Un don relie deux comptes, et c'est ce qui le distingue des autres
     * exports globaux.
     *
     * Le filtre de portefeuille ne peut donc pas suivre un chemin unique vers
     * l'investisseur : un gestionnaire doit voir les dons partis de ses
     * dossiers comme ceux qui y sont arrivés. Les deux le concernent — le
     * premier fait baisser un solde qu'il suit, le second le fait monter.
     */
    protected function donsGlobauxFiltres(Request $request)
    {
        $query = \App\Models\Don::with(['compteSource.investisseur', 'compteDestinataire.investisseur'])
            ->orderBy('date_don');

        if (Auth::user()->role === 'gestionnaire') {
            $portefeuille = Auth::user()->gestionnaire?->id;

            $query->where(function ($q) use ($portefeuille) {
                $q->whereHas('compteSource.investisseur', fn ($i) => $i->where('gestionnaire_id', $portefeuille))
                    ->orWhereHas('compteDestinataire.investisseur', fn ($i) => $i->where('gestionnaire_id', $portefeuille));
            });
        }

        return $this->filtresDates($request, $query, 'date_don');
    }

    public function donsGlobalCsv(Request $request)
    {
        $dons = $this->donsGlobauxFiltres($request)->get();

        return response()->streamDownload(function () use ($dons) {
            $flux = fopen('php://output', 'w');
            fwrite($flux, self::BOM);
            // Pas de colonne « Sens » : donné ou reçu n'a de sens que depuis un
            // compte, et cette liste n'en regarde aucun en particulier.
            fputcsv($flux, [
                'Date', 'Type', 'Donateur', 'Identifiant donateur', 'Compte donateur',
                'Bénéficiaire', 'Identifiant bénéficiaire', 'Compte bénéficiaire',
                'Actions', 'Montant', 'Motif',
            ], ';');

            foreach ($dons as $d) {
                fputcsv($flux, [
                    $d->date_don->format('d/m/Y'),
                    $d->type_don === 'actions' ? 'Actions' : 'Solde',
                    trim(($d->compteSource->investisseur->nom ?? '') . ' ' . ($d->compteSource->investisseur->prenom ?? '')),
                    $d->compteSource->investisseur->identifiant_externe ?? '',
                    $d->compteSource->numero_compte ?? '',
                    trim(($d->compteDestinataire->investisseur->nom ?? '') . ' ' . ($d->compteDestinataire->investisseur->prenom ?? '')),
                    $d->compteDestinataire->investisseur->identifiant_externe ?? '',
                    $d->compteDestinataire->numero_compte ?? '',
                    $d->nombre_actions,
                    $d->montant,
                    $d->motif,
                ], ';');
            }

            fclose($flux);
        }, 'dons_tous_' . now()->format('Y-m-d') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function donsGlobalPdf(Request $request)
    {
        $dons = $this->donsGlobauxFiltres($request)->get();

        $pdf = Pdf::loadView('pdf.liste-dons-global', ['dons' => $dons, 'dateGeneration' => now()])
            ->setPaper('a4', 'landscape');

        return $this->telechargerPdf($pdf, 'Dons_Tous_' . now()->format('Y-m-d') . '.pdf');
    }

    // --- Investisseurs (liste globale) -----------------------------------
    protected function investisseursFiltres(Request $request)
    {
        $query = Investisseur::query()->with('gestionnaire.user');

        if ($recherche = $request->query('recherche')) {
            $query->where(function ($q) use ($recherche) {
                $q->where('nom', 'like', "%{$recherche}%")
                  ->orWhere('prenom', 'like', "%{$recherche}%")
                  ->orWhere('identifiant_externe', 'like', "%{$recherche}%");
            });
        }

        // Comme pour la liste affichée à l'écran (InvestisseurIndex), un gestionnaire
        // est toujours forcé sur son propre portefeuille, quelle que soit la query
        // string envoyée.
        if (Auth::user()->role === 'gestionnaire') {
            $query->where('gestionnaire_id', Auth::user()->gestionnaire?->id);
        } elseif ($gestionnaireId = $request->query('gestionnaire')) {
            $query->where('gestionnaire_id', $gestionnaireId);
        }

        if ($statut = $request->query('statut')) {
            $query->where('statut', $statut);
        }

        return $query->orderBy('nom');
    }

    public function investisseursCsv(Request $request)
    {
        $investisseurs = $this->investisseursFiltres($request)->get();

        return response()->streamDownload(function () use ($investisseurs) {
            $flux = fopen('php://output', 'w');
            fwrite($flux, self::BOM);

            fputcsv($flux, ['Identifiant', 'Nom', 'Prénom', 'Type', 'Téléphone', 'Email', 'Pays', 'Gestionnaire', 'Statut'], ';');

            foreach ($investisseurs as $inv) {
                fputcsv($flux, [
                    $inv->identifiant_externe, $inv->nom, $inv->prenom, $inv->type_personne,
                    $inv->telephone, $inv->email, $inv->pays,
                    $inv->gestionnaire?->user ? $inv->gestionnaire->user->nom . ' ' . $inv->gestionnaire->user->prenom : '',
                    $inv->statut,
                ], ';');
            }

            fclose($flux);
        }, 'investisseurs_' . now()->format('Y-m-d') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function investisseursPdf(Request $request)
    {
        $investisseurs = $this->investisseursFiltres($request)->get();

        $filtresActifs = collect([
            $request->query('recherche') ? "recherche « {$request->query('recherche')} »" : null,
            $request->query('statut') ? "statut {$request->query('statut')}" : null,
        ])->filter()->implode(', ');

        $pdf = Pdf::loadView('pdf.liste-investisseurs', [
            'investisseurs' => $investisseurs,
            'filtresActifs' => $filtresActifs,
            'dateGeneration' => now(),
        ])->setPaper('a4', 'landscape');

        return $this->telechargerPdf($pdf, 'Liste_Investisseurs_' . now()->format('Y-m-d') . '.pdf');
    }

    protected function auditFiltre(Request $request)
    {
        $query = AuditLog::with('user')->latest('created_at');

        if ($recherche = $request->query('recherche')) {
            $query->where(function ($q) use ($recherche) {
                $q->whereHas('user', fn ($u) => $u->where('nom', 'like', "%{$recherche}%")->orWhere('email', 'like', "%{$recherche}%"))
                  ->orWhere('entite_id', $recherche);
            });
        }

        if ($action = $request->query('action')) {
            $query->where('action', $action);
        }

        if ($entite = $request->query('entite')) {
            $query->where('entite', $entite);
        }

        if ($dateDebut = $request->query('date_debut')) {
            $query->whereDate('created_at', '>=', $dateDebut);
        }

        if ($dateFin = $request->query('date_fin')) {
            $query->whereDate('created_at', '<=', $dateFin);
        }

        return $query;
    }

    public function auditCsv(Request $request)
    {
        $entrees = $this->auditFiltre($request)->get();

        return response()->streamDownload(function () use ($entrees) {
            $flux = fopen('php://output', 'w');
            fwrite($flux, self::BOM);

            fputcsv($flux, ['Date', 'Utilisateur', 'Email', 'Action', 'Entité', 'ID entité', 'Avant', 'Après', 'IP'], ';');

            foreach ($entrees as $e) {
                fputcsv($flux, [
                    $e->created_at->format('d/m/Y H:i'),
                    trim(($e->user?->nom ?? '') . ' ' . ($e->user?->prenom ?? '')),
                    $e->user?->email,
                    $e->action, $e->entite, $e->entite_id,
                    $e->donnees_avant ? json_encode($e->donnees_avant, JSON_UNESCAPED_UNICODE) : '',
                    $e->donnees_apres ? json_encode($e->donnees_apres, JSON_UNESCAPED_UNICODE) : '',
                    $e->ip_address,
                ], ';');
            }

            fclose($flux);
        }, 'journal_audit_' . now()->format('Y-m-d') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function auditPdf(Request $request)
    {
        $entrees = $this->auditFiltre($request)->get();

        $filtresActifs = collect([
            $request->query('recherche') ? "recherche « {$request->query('recherche')} »" : null,
            $request->query('action') ? "action {$request->query('action')}" : null,
            $request->query('entite') ? "entité {$request->query('entite')}" : null,
        ])->filter()->implode(', ');

        $pdf = Pdf::loadView('pdf.journal-audit', [
            'entrees' => $entrees,
            'filtresActifs' => $filtresActifs,
            'dateGeneration' => now(),
        ])->setPaper('a4', 'landscape');

        return $this->telechargerPdf($pdf, 'Journal_Audit_' . now()->format('Y-m-d') . '.pdf');
    }
}
