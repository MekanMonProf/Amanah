<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La plupart des investisseurs n'ont pas d'email (téléphone quasi universel, email minoritaire) —
 * l'accès portail doit donc pouvoir reposer sur le téléphone seul comme identifiant de connexion.
 * doctrine/dbal n'étant pas installé, on modifie la colonne en SQL brut plutôt que via ->change().
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE users MODIFY email VARCHAR(255) NULL');

        Schema::table('users', function (Blueprint $table) {
            $table->unique('telephone');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['telephone']);
        });

        DB::statement('ALTER TABLE users MODIFY email VARCHAR(255) NOT NULL');
    }
};
