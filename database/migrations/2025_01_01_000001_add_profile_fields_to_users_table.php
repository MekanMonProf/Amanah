<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // À appliquer sur la migration "create_users_table" par défaut de Laravel.
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('nom', 150)->after('id');
            $table->string('prenom', 150)->after('nom');
            $table->string('telephone', 30)->nullable()->after('email');
            $table->enum('role', ['direction', 'administrateur', 'gestionnaire', 'lecture'])
                  ->default('gestionnaire')->after('password');
            $table->boolean('actif')->default(true)->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['nom', 'prenom', 'telephone', 'role', 'actif']);
        });
    }
};
