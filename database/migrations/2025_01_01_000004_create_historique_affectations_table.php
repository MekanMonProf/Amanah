<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('historique_affectations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('investisseur_id')->constrained('investisseurs');
            $table->foreignId('ancien_gestionnaire_id')->nullable()->constrained('gestionnaires');
            $table->foreignId('nouveau_gestionnaire_id')->constrained('gestionnaires');
            $table->date('date_transfert');
            $table->string('motif')->nullable();
            $table->foreignId('effectue_par')->nullable()->constrained('users');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('historique_affectations');
    }
};
