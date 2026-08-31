<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investisseurs', function (Blueprint $table) {
            $table->id();
            $table->string('identifiant_externe', 20)->unique()
                  ->comment('ex: A0191 - conservé depuis l’Excel pour compatibilité');
            $table->enum('type_personne', ['physique', 'morale'])->default('physique');
            $table->string('nom', 150);
            $table->string('prenom', 150)->nullable();
            $table->string('telephone', 30)->nullable();
            $table->string('type_identification', 100)->nullable();
            $table->string('numero_identification', 100)->nullable();
            $table->date('date_expiration_piece')->nullable();
            $table->string('pays', 100)->nullable();
            $table->foreignId('gestionnaire_id')->nullable()->constrained('gestionnaires');
            $table->enum('statut', ['actif', 'inactif'])->default('actif');
            $table->timestamps();

            $table->index(['nom', 'prenom']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investisseurs');
    }
};
