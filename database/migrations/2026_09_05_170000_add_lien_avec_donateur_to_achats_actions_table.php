<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lien entre le donateur et le défunt honoré (« Père », « Mère », « Ami »...).
 *
 * Texte libre volontairement, comme investisseurs.beneficiaire_lien et
 * heritiers.lien_parente : la liste des liens possibles n'est pas fermée.
 * Facultatif — on honore parfois une figure sans lien personnel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('achats_actions', function (Blueprint $table) {
            $table->string('lien_avec_donateur', 100)->nullable()->after('en_memoire_de');
        });
    }

    public function down(): void
    {
        Schema::table('achats_actions', function (Blueprint $table) {
            $table->dropColumn('lien_avec_donateur');
        });
    }
};
