<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('achats_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('compte_id')->constrained('comptes_investissement');
            $table->string('numero_achat', 30)->unique()->comment('ex: ACH-1');
            $table->date('date_achat');
            $table->enum('type_achat', ['initial', 'rajout', 'complement', 'benefice']);
            $table->unsignedInteger('nombre_actions');
            $table->decimal('prix_unitaire', 15, 2);
            $table->decimal('montant', 15, 2);
            $table->string('mode_paiement', 50)->nullable();
            $table->string('reference_facture', 100)->nullable();
            $table->string('photo_facture_path')->nullable();
            $table->text('observations')->nullable();
            $table->foreignId('saisi_par')->nullable()->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('achats_actions');
    }
};
