<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('radiations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('compte_id')->constrained('comptes_investissement');
            $table->string('numero_radiation', 30)->unique()->comment('ex: R001');
            $table->date('date_radiation');
            $table->unsignedInteger('nombre_actions_radiees');
            $table->decimal('prix_unitaire_action', 15, 2);
            $table->decimal('montant_total', 15, 2);
            $table->string('mode_paiement', 50)->nullable();
            $table->string('reference_facture', 100)->nullable();
            $table->date('mois_previsionnel_paiement')->nullable();
            $table->text('observations')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('radiations');
    }
};
