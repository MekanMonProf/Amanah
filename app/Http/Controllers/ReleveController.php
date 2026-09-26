<?php

namespace App\Http\Controllers;

use App\Models\CompteInvestissement;
use App\Models\Investisseur;
use App\Support\RestreintAuPortefeuilleGestionnaire;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReleveController extends Controller
{
    use \App\Http\Controllers\Concerns\RendPdf;

    use RestreintAuPortefeuilleGestionnaire;

    public function pourGestionnaire(Request $request, Investisseur $investisseur)
    {
        $this->assurerAccesGestionnaire($investisseur);

        return $this->genererPdf($investisseur, $request->query('date_debut'), $request->query('date_fin'));
    }

    public function pourInvestisseur(Request $request)
    {
        $investisseur = Auth::user()->investisseurLie;

        abort_if(! $investisseur, 404);

        return $this->genererPdf($investisseur, $request->query('date_debut'), $request->query('date_fin'));
    }

    /**
     * Solde du compte juste AVANT la période (= solde de départ du relevé).
     * Basé sur l'ordre réel d'insertion (id), jamais sur la date métier — voir la note
     * dans CompteInvestissement::solde().
     */
    protected function soldeAvant(CompteInvestissement $compte, ?string $dateDebut): float
    {
        if (! $dateDebut) {
            return 0.0;
        }

        $ecriture = $compte->ecritures()->whereDate('date_ecriture', '<', $dateDebut)->reorder('id', 'desc')->first();

        return $ecriture ? (float) $ecriture->solde_apres : 0.0;
    }

    /**
     * Solde du compte à la fin de la période (ou solde actuel si aucune date de fin donnée).
     */
    protected function soldeJusque(CompteInvestissement $compte, ?string $dateFin): float
    {
        $query = $compte->ecritures();

        if ($dateFin) {
            $query->whereDate('date_ecriture', '<=', $dateFin);
        }

        $ecriture = $query->reorder('id', 'desc')->first();

        return $ecriture ? (float) $ecriture->solde_apres : 0.0;
    }

    protected function genererPdf(Investisseur $investisseur, ?string $dateDebut, ?string $dateFin)
    {
        $comptes = $investisseur->comptes()->get()->map(function ($compte) use ($dateDebut, $dateFin) {
            $achats = $compte->achats()->orderBy('date_achat');
            $dividendes = $compte->dividendes()->orderBy('periode');
            $ecritures = $compte->ecritures()->reorder('id', 'asc');

            if ($dateDebut) {
                $achats->whereDate('date_achat', '>=', $dateDebut);
                $dividendes->whereDate('periode', '>=', $dateDebut);
                $ecritures->whereDate('date_ecriture', '>=', $dateDebut);
            }
            if ($dateFin) {
                $achats->whereDate('date_achat', '<=', $dateFin);
                $dividendes->whereDate('periode', '<=', $dateFin);
                $ecritures->whereDate('date_ecriture', '<=', $dateFin);
            }

            return [
                'compte' => $compte,
                'nombre_actions' => $compte->nombreActions(),
                'solde_avant' => $this->soldeAvant($compte, $dateDebut),
                'solde_fin' => $this->soldeJusque($compte, $dateFin),
                'achats' => $achats->get(),
                'dividendes' => $dividendes->get(),
                'ecritures' => $ecritures->get(),
            ];
        });

        $pdf = Pdf::loadView('pdf.releve-investisseur', [
            'investisseur' => $investisseur,
            'comptes' => $comptes,
            'dateDebut' => $dateDebut,
            'dateFin' => $dateFin,
            'dateGeneration' => now(),
        ])->setPaper('a4', 'portrait');

        $suffixe = $dateDebut || $dateFin ? '_periode' : '';
        $nomFichier = 'Releve_' . $investisseur->identifiant_externe . $suffixe . '_' . now()->format('Y-m-d') . '.pdf';

        return $this->rendrePdf($pdf, $nomFichier);
    }
}
