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
    use \App\Http\Controllers\Concerns\RendPdf;

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
        $achat->load('compte.investisseur', 'offertPar');

        // Une present (hommage à un défunt ou cadeau à un vivant) est portée au compte du Waqf :
        // c'est le donateur, et non le titulaire du compte, qui a droit à l'attestation.
        if ($achat->estUnPresent()) {
            $user = Auth::user();
            if ($user->role === 'investisseur' && $achat->offert_par_investisseur_id !== optional($user->investisseurLie)->id) {
                abort(403);
            }
        } else {
            $this->verifierAcces($achat->compte);
        }

        $pdf = Pdf::loadView('pdf.attestation-achat', [
            'achat' => $achat,
            'compte' => $achat->compte,
            'investisseur' => $achat->compte->investisseur,
            'dateGeneration' => now(),
        ])->setPaper('a4', 'portrait');

        // Le nom du fichier suit le titre du document : un présent Waqf n'est pas une
        // attestation d'achat ordinaire, et le destinataire le classe sous ce nom.
        $prefixe = match (true) {
            $achat->estUnPresent() => 'Certificat_Hommage_Generosite_',
            $achat->estUnReinvestissement() => 'Attestation_Reinvestissement_',
            default => 'Attestation_Achat_',
        };

        return $this->rendrePdf($pdf, $prefixe . $achat->numero_achat . '.pdf');
    }

    public function radiation(Radiation $radiation)
    {
        $radiation->load('compte.investisseur');
        $this->verifierAcces($radiation->compte);

        $montantVerse = $this->montantVerse($radiation);

        $pdf = Pdf::loadView('pdf.attestation-radiation', [
            'radiation' => $radiation,
            'compte' => $radiation->compte,
            'investisseur' => $radiation->compte->investisseur,
            'montantVerse' => $montantVerse,
            'statutPaiement' => $montantVerse >= $radiation->montant_total ? 'Payé' : ($montantVerse > 0 ? 'Partiellement payé' : 'Non payé'),
            'dateGeneration' => now(),
        ])->setPaper('a4', 'portrait');

        $prefixe = $radiation->estUneSuccession()
            ? 'Attestation_Liquidation_Succession_'
            : 'Attestation_Radiation_';

        return $this->rendrePdf($pdf, $prefixe . $radiation->numero_radiation . '.pdf');
    }

    /**
     * Ce qui a déjà été versé au titre d'une radiation.
     *
     * Une radiation ordinaire est payée depuis l'écran des versements, qui rattache
     * l'écriture à la radiation elle-même. Une succession, non : le règlement passe
     * par PaiementSuccessionCreate, qui vide le compte du défunt d'un seul geste et
     * rattache l'écriture au défunt (`succession_deces`). Chercher uniquement des
     * paiements `radiations` faisait donc toujours conclure « Non payé » sur une
     * succession pourtant réglée.
     *
     * Ce versement unique solde aussi la trésorerie qui dormait sur le compte avant
     * la liquidation : on ne retient donc que ce qui est imputable à la radiation,
     * plafonné à son montant, plutôt que la somme brute qui la dépasserait.
     */
    private function montantVerse(Radiation $radiation): float
    {
        $requete = EcritureCompteFinancier::where('type_ecriture', 'paiement');

        if ($radiation->estUneSuccession()) {
            $requete->where('compte_id', $radiation->compte_id)
                ->where('reference_type', 'succession_deces')
                ->whereDate('date_ecriture', '>=', $radiation->date_radiation);
        } else {
            $requete->where('reference_type', 'radiations')
                ->where('reference_id', $radiation->id);
        }

        $verse = abs((float) $requete->sum('montant'));

        return $radiation->estUneSuccession()
            ? min($verse, (float) $radiation->montant_total)
            : $verse;
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

        return $this->rendrePdf($pdf, 'Attestation_Versement_' . $ecriture->id . '_' . $ecriture->date_ecriture->format('Y-m-d') . '.pdf');
    }
}
