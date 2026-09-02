<?php

namespace App\Http\Controllers;

use App\Support\ImportateurDonnees;

/**
 * Fournit le fichier modèle à remplir avant un import.
 *
 * Volontairement limité à la ligne d'en-têtes : une ligne d'exemple oubliée dans le
 * fichier créerait un faux dossier en base. Les exemples de valeurs et l'explication
 * de chaque colonne sont affichés sur la page d'import elle-même.
 *
 * Format CSV point-virgule avec BOM, comme les exports de l'application : Excel
 * l'ouvre directement en colonnes, accents compris.
 */
class ModeleImportController extends Controller
{
    public function telecharger(string $type)
    {
        abort_unless(array_key_exists($type, ImportateurDonnees::TYPES), 404);

        // Les libellés (pas les noms techniques de colonne) : un client qui ouvre le
        // modèle doit voir "Date de naissance", pas "date_naissance". L'import les
        // reconnaît de toute façon (voir ImportateurDonnees::alias()), donc rien ne
        // change côté lecture.
        $entetes = array_map(fn ($colonne) => $colonne['libelle'], ImportateurDonnees::schema($type));
        $nom = 'modele_import_' . $type . '.csv';

        return response()->streamDownload(function () use ($entetes) {
            $flux = fopen('php://output', 'w');
            fwrite($flux, "\xEF\xBB\xBF");
            fputcsv($flux, $entetes, ';');
            fclose($flux);
        }, $nom, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
