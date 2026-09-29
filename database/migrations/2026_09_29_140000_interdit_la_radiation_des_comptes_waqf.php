<?php

use App\Models\PolitiqueInvestissement;
use Illuminate\Database\Migrations\Migration;

/**
 * Un compte waqf ne se radie pas.
 *
 * Des parts données au waqf sont sorties du patrimoine de celui qui les a
 * données : on ne les reprend pas. La règle existait déjà en creux — le capital
 * ne se cède pas — mais la radiation restait ouverte, ce qui revenait à offrir
 * la même sortie par une autre porte.
 *
 * La succession n'est pas concernée : au décès, un compte waqf n'est jamais
 * liquidé, son capital est redirigé vers l'œuvre caritative. Seule la radiation
 * volontaire disparaît, et aucune n'existe en base sur un compte waqf.
 */
return new class extends Migration
{
    public function up(): void
    {
        PolitiqueInvestissement::where('categorie', 'waqf')
            ->update(['radiation_autorisee' => false]);
    }

    public function down(): void
    {
        PolitiqueInvestissement::where('categorie', 'waqf')
            ->update(['radiation_autorisee' => true]);
    }
};
