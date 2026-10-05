<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pendant de la règle d'éligibilité, pour la sortie.
 *
 * L'entrée avait sa règle — un achat trop tardif ne compte qu'au mois suivant —
 * mais la sortie n'en avait aucune : dès qu'une radiation était enregistrée, les
 * actions disparaissaient du dividende du mois en cours. Or la maison paie le mois
 * entier à qui sort en cours de mois : les radiations de 2025 s'étalent du 22 mars
 * au 15 décembre, et chacune a touché le dividende de son mois.
 *
 * Valeur par défaut : 31 jours, c'est-à-dire le mois entier, qui est la pratique
 * constatée. 0 rétablirait l'effet immédiat.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parametres_dividendes', function (Blueprint $table) {
            $table->unsignedTinyInteger('delai_radiation_jours')->default(31)->after('delai_eligibilite_jours')
                ->comment('Nombre de jours avant la fin du mois : une radiation effectuée dans cette fenêtre laisse les actions toucher le dividende du mois. 0 = effet immédiat, 31 = le mois entier.');
        });
    }

    public function down(): void
    {
        Schema::table('parametres_dividendes', function (Blueprint $table) {
            $table->dropColumn('delai_radiation_jours');
        });
    }
};
