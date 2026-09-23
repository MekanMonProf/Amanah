<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rend traduisibles les observations écrites par l'application.
 *
 * La colonne `observations` ne bouge pas : elle garde la phrase française, qui
 * reste la version de référence pour les exports CSV, les PDF et l'audit. On
 * ajoute à côté la structure — une clé de traduction et ses paramètres — que
 * l'affichage utilise quand elle est présente.
 *
 * Les observations saisies par une personne n'ont pas de clé : elles restent
 * telles quelles, dans la langue où elles ont été écrites.
 */
return new class extends Migration
{
    private const TABLES = ['ecritures_compte_financier', 'achats_actions'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->string('observation_cle', 255)->nullable()->after('observations');
                $t->json('observation_parametres')->nullable()->after('observation_cle');
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn(['observation_cle', 'observation_parametres']);
            });
        }
    }
};
