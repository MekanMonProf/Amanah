<?php

namespace App\Http\Controllers;

use App\Models\EcritureCompteFinancier;
use App\Support\Droits;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;

/**
 * Le reçu d'une opération, en PDF.
 *
 * Deux portes y mènent. Celle du personnel passe par l'authentification et les
 * droits ordinaires. Celle de l'investisseur ne passe par rien : le lien qu'il a
 * reçu sur WhatsApp porte sa propre signature et expire, parce que la plupart des
 * investisseurs n'ont pas de compte et que leur en demander un pour lire un reçu
 * reviendrait à ne pas le leur envoyer.
 *
 * La signature est vérifiée par le middleware `signed` posé sur la route : une
 * URL retouchée, ne serait-ce que d'un chiffre sur l'identifiant, est refusée.
 * Sans cela, essayer les numéros à la suite donnerait les reçus de tout le monde.
 */
class RecuController extends Controller
{
    use Concerns\RendPdf;

    /** Consultation depuis l'application, par une personne connectée. */
    public function interne(EcritureCompteFinancier $ecriture)
    {
        $ecriture->load('compte.investisseur');

        $investisseurLie = Auth::user()->investisseurLie;

        // Un investisseur ne voit que ses propres reçus ; le personnel voit ceux
        // des dossiers auxquels le module Investisseurs lui donne accès.
        abort_unless(
            ($investisseurLie && $investisseurLie->id === $ecriture->compte->investisseur_id)
                || Droits::peutLire('investisseurs'),
            403,
        );

        return $this->rendre($ecriture);
    }

    /** Ouverture depuis le lien signé, sans compte. */
    public function public(EcritureCompteFinancier $ecriture)
    {
        $ecriture->load('compte.investisseur');

        return $this->rendre($ecriture);
    }

    private function rendre(EcritureCompteFinancier $ecriture)
    {
        $numero = 'REC-' . str_pad((string) $ecriture->id, 6, '0', STR_PAD_LEFT);

        $pdf = Pdf::loadView('pdf.recu-operation', [
            'ecriture' => $ecriture,
            'compte' => $ecriture->compte,
            'investisseur' => $ecriture->compte->investisseur,
            'numero' => $numero,
            'dateGeneration' => now(),
        ])->setPaper('a4', 'portrait');

        return $this->rendrePdf($pdf, 'Recu_' . $numero . '.pdf');
    }
}
