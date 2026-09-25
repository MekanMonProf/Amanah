<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Lecture des pièces d'identité
    |--------------------------------------------------------------------------
    |
    | La lecture se fait sur ce serveur, avec Tesseract : aucune pièce d'identité
    | n'est envoyée à un tiers. Si le binaire est absent, la fonction s'éteint
    | d'elle-même et la saisie reste manuelle — rien ne casse.
    |
    | « binaire » peut être un simple nom si Tesseract est dans le PATH, ou un
    | chemin complet (sous Windows, typiquement
    | C:\Program Files\Tesseract-OCR\tesseract.exe).
    |
    */

    'tesseract' => [
        'binaire' => env('TESSERACT_BIN', 'tesseract'),

        // Secondes avant d'abandonner : une lecture normale prend 1 à 3 s.
        'delai' => (int) env('TESSERACT_TIMEOUT', 20),

        // La MRZ n'utilise que cet alphabet. Le restreindre évite que l'OCR
        // propose des minuscules ou de la ponctuation là où il hésite.
        'alphabet' => 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789<',
    ],

];
