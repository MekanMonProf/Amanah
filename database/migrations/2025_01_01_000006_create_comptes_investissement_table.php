<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comptes_investissement', function (Blueprint $table) {
            $table->id();
            $table->foreignId('investisseur_id')->constrained('investisseurs');
            $table->enum('categorie', ['commercial', 'waqf']);
            $table->string('numero_compte', 30)->unique()
                  ->comment('ex: A0191-COM / A0191-WAQF');
            $table->boolean('reinvestissement_auto')->default(true);
            $table->enum('statut', ['actif', 'radie', 'clos'])->default('actif');
            $table->date('date_ouverture');
            $table->date('date_radiation')->nullable();
            $table->timestamps();

            $table->unique(['investisseur_id', 'categorie']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comptes_investissement');
    }
};
