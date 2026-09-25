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

        /*
        | Données de langue. Le paquet livré avec Tesseract ne contient que
        | l'anglais, qui suffit à la bande — celle-ci n'est pas du texte, et le
        | modèle français la lit d'ailleurs moins bien. Le français ne sert qu'au
        | texte imprimé de la carte, accentué.
        |
        | Le fichier ne se trouve pas dans le dépôt : trop lourd. Pour le poser
        | sur un nouveau serveur :
        |
        |   curl -L --create-dirs -o storage/app/tessdata/fra.traineddata https://github.com/tesseract-ocr/tessdata/raw/main/fra.traineddata
        |
        | Sans lui, la bande reste lue et le texte imprimé ne l'est pas.
        */
        'dossier_donnees' => env('TESSERACT_TESSDATA', storage_path('app/tessdata')),
        'langue_texte' => env('TESSERACT_LANGUE_TEXTE', 'fra'),
    ],

];
