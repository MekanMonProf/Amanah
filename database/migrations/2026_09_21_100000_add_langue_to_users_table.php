<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Langue d interface choisie par l utilisateur.
 *
 * Stockee sur le compte plutot qu en session : un gestionnaire qui prefere l arabe le
 * retrouve depuis n importe quel poste, et l information survit a une reconnexion.
 * Les documents PDF restent en francais quelle que soit cette valeur — seule
 * l interface est traduite.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('langue', ['fr', 'en', 'ar'])->default('fr')->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('langue');
        });
    }
};
