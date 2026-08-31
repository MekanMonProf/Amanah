<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parametres_dividendes', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('delai_eligibilite_jours')->default(0)
                  ->comment('Nombre de jours avant la fin du mois : un achat effectué dans cette fenêtre ne compte qu\'à partir du mois suivant. 0 = mois entier toujours éligible.');
            $table->timestamps();
        });

        // Ligne unique de paramétrage, créée une fois pour toutes
        DB::table('parametres_dividendes')->insert([
            'delai_eligibilite_jours' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('parametres_dividendes');
    }
};
