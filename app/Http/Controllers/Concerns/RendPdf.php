<?php

namespace App\Http\Controllers\Concerns;

use Barryvdh\DomPDF\PDF;

/**
 * Un seul endroit décide si un PDF s'affiche ou se télécharge.
 *
 * Par défaut il s'affiche dans l'onglet. Le lecteur du navigateur propose déjà
 * l'enregistrement et l'impression : on voit avant de garder, au lieu de garder
 * pour voir. C'est surtout vrai d'une attestation, qu'on relit avant de la
 * remettre à quelqu'un.
 *
 * Tous les liens portent déjà target="_blank" : jusqu'ici ils ouvraient un onglet
 * qui se vidait aussitôt le fichier poussé. L'aperçu lui donne enfin un contenu.
 *
 * `?telecharger=1` force le téléchargement, pour qui veut le fichier sans passer
 * par le lecteur — et pour les cas où ce dernier fait défaut.
 */
trait RendPdf
{
    protected function rendrePdf(PDF $pdf, string $nomFichier)
    {
        return request()->boolean('telecharger')
            ? $pdf->download($nomFichier)
            : $pdf->stream($nomFichier);
    }
}
