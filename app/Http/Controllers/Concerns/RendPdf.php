<?php

namespace App\Http\Controllers\Concerns;

use Barryvdh\DomPDF\PDF;

/**
 * Un seul endroit décide si un PDF s'affiche ou se télécharge.
 *
 * Les deux usages ne se ressemblent pas. Une attestation ou un relevé se relit
 * avant d'être remis à quelqu'un : ils s'ouvrent dans le lecteur du navigateur,
 * qui offre déjà l'enregistrement et l'impression. Un export, lui, est un fichier
 * qu'on veut sur son disque — l'afficher d'abord ne ferait qu'ajouter un clic.
 *
 * D'où un défaut par type de document, que chaque appel exprime, plutôt qu'une
 * règle unique qui conviendrait mal à l'un des deux.
 *
 * Les deux comportements restent accessibles à la demande : `?telecharger=1` sur
 * un document, `?apercu=1` sur un export.
 */
trait RendPdf
{
    protected function rendrePdf(PDF $pdf, string $nomFichier, bool $apercuParDefaut = true)
    {
        return $this->veutUnApercu($apercuParDefaut)
            ? $pdf->stream($nomFichier)
            : $pdf->download($nomFichier);
    }

    /** Un export : le fichier d'abord, l'aperçu seulement si on le demande. */
    protected function telechargerPdf(PDF $pdf, string $nomFichier)
    {
        return $this->rendrePdf($pdf, $nomFichier, apercuParDefaut: false);
    }

    private function veutUnApercu(bool $defaut): bool
    {
        $requete = request();

        if ($requete->has('telecharger')) {
            return ! $requete->boolean('telecharger');
        }

        if ($requete->has('apercu')) {
            return $requete->boolean('apercu');
        }

        return $defaut;
    }
}
