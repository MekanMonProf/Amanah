<?php

namespace App\Http\Controllers;

use App\Models\AchatAction;
use App\Models\CompteInvestissement;
use App\Models\EcritureCompteFinancier;
use App\Models\Radiation;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;

class AttestationController extends Controller
{
    /**
     * Un investisseur ne peut voir que les attestations de son propre compte.
     * Le personnel (tous les autres rôles) peut voir n'importe laquelle — l'accès aux
     * pages de gestion elles-mêmes est déjà filtré par le middleware de rôle.
     */
    protected function verifierAcces(CompteInvestissement $compte): void
    {
        $user = Auth::user();

        if ($user->role === 'investisseur' && $compte->investisseur_id !== optional($user->investisseurLie)->id) {
            abort(403);
        }
    }

    public function achat(AchatAction $achat)
    {
        $achat->load('compte.investisseur');
        $this->verifierAcces($achat->compte);

        $pdf = Pdf::loadView('pdf.attestation-achat', [
            'achat' => $achat,
            'compte' => $achat->compte,
            'investisseur' => $achat->compte->investisseur,
            'dateGeneration' => now(),
        ])->setPaper('a4', 'portrait');

        return $pdf->download('Attestation_Achat_' . $achat->numero_achat . '.pdf');
    }

    public function radiation(Radiation $radiation)
    {
        $radiation->load('compte.investisseur');
        $this->verifierAcces($radiation->compte);

        $montantVerse = (float) EcritureCompteFinancier::where('reference_type', 'radiations')
            ->where('reference_id', $radiation->id)
            ->where('type_ecriture', 'paiement')
            ->sum('montant');
        $montantVerse = abs($montantVerse);

        $pdf = Pdf::loadView('pdf.attestation-radiation', [
            'radiation' => $radiation,
            'compte' => $radiation->compte,
            'investisseur' => $radiation->compte->investisseur,
            'montantVerse' => $montantVerse,
            'statutPaiement' => $montantVerse >= $radiation->montant_total ? 'Payé' : ($montantVerse > 0 ? 'Partiellement payé' : 'Non payé'),
            'dateGeneration' => now(),
        ])->setPaper('a4', 'portrait');

        return $pdf->download('Attestation_Radiation_' . $radiation->numero_radiation . '.pdf');
    }

    public function paiement(EcritureCompteFinancier $ecriture)
    {
        abort_unless($ecriture->type_ecriture === 'paiement', 404);

        $ecriture->load('compte.investisseur');
        $this->verifierAcces($ecriture->compte);

        $pdf = Pdf::loadView('pdf.attestation-paiement', [
            'ecriture' => $ecriture,
            'compte' => $ecriture->compte,
            'investisseur' => $ecriture->compte->investisseur,
            'dateGeneration' => now(),
        ])->setPaper('a4', 'portrait');

        return $pdf->download('Attestation_Versement_' . $ecriture->id . '_' . $ecriture->date_ecriture->format('Y-m-d') . '.pdf');
    }
}
