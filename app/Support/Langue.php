<?php

namespace App\Support;

/**
 * Les langues d'interface proposées. Les documents PDF et les emails restent en français
 * quelle que soit la langue choisie — seule l'interface est traduite.
 */
class Langue
{
    /** Code => [libellé dans la langue elle-même, sens d'écriture]. */
    public const DISPONIBLES = [
        'fr' => ['libelle' => 'Français', 'sens' => 'ltr'],
        'en' => ['libelle' => 'English', 'sens' => 'ltr'],
        'ar' => ['libelle' => 'العربية', 'sens' => 'rtl'],
    ];

    public const DEFAUT = 'fr';

    public static function estValide(?string $code): bool
    {
        return $code !== null && array_key_exists($code, self::DISPONIBLES);
    }

    /** Retombe sur le français plutôt que d'échouer : une langue inconnue reste affichable. */
    public static function normaliser(?string $code): string
    {
        return self::estValide($code) ? $code : self::DEFAUT;
    }

    public static function sens(?string $code = null): string
    {
        return self::DISPONIBLES[self::normaliser($code ?? app()->getLocale())]['sens'];
    }

    public static function estRtl(?string $code = null): bool
    {
        return self::sens($code) === 'rtl';
    }

    public static function libelle(?string $code): string
    {
        return self::DISPONIBLES[self::normaliser($code)]['libelle'];
    }
}
