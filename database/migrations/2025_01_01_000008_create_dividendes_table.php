<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dividendes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('compte_id')->constrained('comptes_investissement');
            $table->date('periode')->comment('premier jour du mois concerné');
            $table->unsignedInteger('nombre_actions');
            $table->decimal('benefice_par_action', 15, 4);
            $table->decimal('montant_calcule', 15, 2);
            $table->enum('statut', ['calcule', 'credite', 'annule'])->default('calcule');
            $table->timestamps();

            $table->unique(['compte_id', 'periode']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dividendes');
    }
};
