<?php

namespace App\Http\Controllers;

use App\Models\DocumentOfficiel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Sert un document officiel.
 *
 * Il vit sur le disque privé comme les pièces de dossier, et ne sort que par
 * ici. La différence tient à la règle : les pièces d'un dossier ne regardent
 * que leur titulaire, un document officiel regarde tout le monde — mais tout
 * le monde qui a un compte. La route est donc derrière l'authentification, et
 * rien d'autre.
 */
class DocumentOfficielController extends Controller
{
    use \App\Http\Controllers\Concerns\RendPdf;

    public function ouvrir(Request $request, DocumentOfficiel $document): StreamedResponse
    {
        abort_unless(Storage::disk(DocumentOfficiel::DISQUE)->exists($document->fichier_path), 404);

        // Même partage des rôles que pour les autres documents : on lit dans le
        // navigateur, on télécharge quand on le demande.
        $disposition = $request->boolean('telecharger') ? 'attachment' : 'inline';

        return Storage::disk(DocumentOfficiel::DISQUE)->response(
            $document->fichier_path,
            $document->nom_fichier,
            ['Content-Disposition' => $disposition . '; filename="' . $document->nom_fichier . '"'],
        );
    }
}
