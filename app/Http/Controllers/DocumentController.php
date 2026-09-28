<?php

namespace App\Http\Controllers;

use App\Support\Document;
use App\Support\Droits;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * La seule porte par laquelle sort une pièce jointe.
 *
 * Trois vérifications avant de servir quoi que ce soit : le type demandé figure
 * au catalogue, la colonne aussi, et la personne a le droit de voir ce dossier.
 * Sans la deuxième, un nom de colonne venu de l'URL permettrait de lire
 * n'importe quel champ de la ligne ; sans la troisième, on aurait seulement
 * déplacé le problème d'une adresse publique vers une adresse authentifiée.
 */
class DocumentController extends Controller
{
    public function ouvrir(string $type, int $id, string $colonne): StreamedResponse
    {
        $source = Document::source($type);

        abort_if($source === null, 404);
        abort_unless(Document::colonneServable($type, $colonne), 404);

        $porteur = $source['modele']::findOrFail($id);
        $chemin = $porteur->$colonne;

        abort_if(blank($chemin), 404);

        $this->verifierAcces($porteur);

        abort_unless(Storage::disk(Document::DISQUE)->exists($chemin), 404);

        // Affiché dans l'onglet plutôt que téléchargé : on relit une pièce
        // d'identité pour vérifier une saisie, on ne la classe pas.
        return Storage::disk(Document::DISQUE)->response($chemin, basename($chemin), [
            'Content-Disposition' => 'inline; filename="' . basename($chemin) . '"',
        ]);
    }

    /**
     * Un investisseur n'ouvre que les pièces de son propre dossier ; le personnel
     * ouvre celles des dossiers auxquels le module Investisseurs lui donne accès.
     */
    private function verifierAcces(\Illuminate\Database\Eloquent\Model $porteur): void
    {
        $investisseurLie = Auth::user()->investisseurLie;

        if ($investisseurLie) {
            $concernes = array_map(
                fn ($investisseur) => $investisseur->id,
                Document::investisseursConcernes($porteur),
            );

            abort_unless(in_array($investisseurLie->id, $concernes, true), 403);

            return;
        }

        Droits::exiger('investisseurs');
    }
}
