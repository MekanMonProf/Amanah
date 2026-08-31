<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('baremes_dividendes', function (Blueprint $table) {
            $table->id();
            $table->date('periode')->comment('premier jour du mois concerné');
            $table->enum('categorie', ['commercial', 'waqf']);
            $table->decimal('benefice_par_action', 15, 4);
            $table->foreignId('fixe_par')->nullable()->constrained('users');
            $table->timestamps();

            $table->unique(['periode', 'categorie']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('baremes_dividendes');
    }
};
