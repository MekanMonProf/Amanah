<?php

namespace App\Http\Controllers;

use App\Models\EcritureCompteFinancier;
use App\Models\Investisseur;
use App\Models\Radiation;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;

class AttestationSuccessionController extends Controller
{
    /**
     * Attestation dédiée pour un versement effectué dans le cadre d'un règlement de succession
     * (mode "Paiement direct") — reprend la date de décès, le détail des actions liquidées et
     * le montant total perçu, en plus des informations d'une attestation de versement classique.
     */
    public function versement(EcritureCompteFinancier $ecriture)
    {
        abort_unless($ecriture->type_ecriture === 'paiement' && $ecriture->reference_type === 'succession_deces', 404);

        $ecriture->load('compte.investisseur');
        $compte = $ecriture->compte;

        $user = Auth::user();
        if ($user->role === 'investisseur') {
            abort_if($compte->investisseur_id !== optional($user->investisseurLie)->id, 403);
        }

        $defunt = Investisseur::findOrFail($ecriture->reference_id);
        $mandataire = $defunt->heritiers()->first();

        $radiation = Radiation::where('compte_id', $compte->id)
            ->where('numero_radiation', 'like', 'RAD-SUCC-%')
            ->latest('id')
            ->first();

        $montantVerse = abs($ecriture->montant);
        $montantActions = $radiation ? (float) $radiation->montant_total : 0;
        $soldeHorsActions = $montantVerse - $montantActions;

        $pdf = Pdf::loadView('pdf.attestation-succession', [
            'ecriture' => $ecriture,
            'compte' => $compte,
            'defunt' => $defunt,
            'mandataire' => $mandataire,
            'radiation' => $radiation,
            'montantActions' => $montantActions,
            'soldeHorsActions' => max($soldeHorsActions, 0),
            'montantVerse' => $montantVerse,
            'dateGeneration' => now(),
        ])->setPaper('a4', 'portrait');

        return $pdf->download('Attestation_Deces_' . $defunt->identifiant_externe . '_' . now()->format('Y-m-d') . '.pdf');
    }
}
