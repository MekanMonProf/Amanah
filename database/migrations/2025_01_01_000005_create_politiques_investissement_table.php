<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('politiques_investissement', function (Blueprint $table) {
            $table->id();
            $table->enum('categorie', ['commercial', 'waqf'])->unique();
            $table->boolean('eligible_dividendes')->default(true);
            $table->boolean('reinvestissement_par_defaut')->default(true);
            $table->boolean('versement_dividendes_possible')->default(true);
            $table->boolean('cession_autorisee')->default(true);
            $table->boolean('radiation_autorisee')->default(true);
            $table->decimal('prix_unitaire_action', 15, 2)->default(25000.00);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('politiques_investissement');
    }
};
