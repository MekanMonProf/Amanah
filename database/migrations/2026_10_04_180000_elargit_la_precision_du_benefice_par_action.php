<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Le bénéfice par action a besoin de plus de quatre décimales.
 *
 * Ce taux n'est pas choisi, il est calculé : bénéfice de la période divisé par le
 * nombre d'actions en circulation. Il tombe donc rarement rond — avril 2025 valait
 * 439,34933237098522 CFA par action. Arrondi à quatre décimales, puis multiplié par
 * un portefeuille de plus de mille actions, l'écart devenait visible : le montant
 * crédité ne retombait plus sur « actions × taux », et le contrôle de cohérence le
 * signalait à juste titre.
 *
 * Huit décimales suffisent : sur le plus gros portefeuille repris (1 347 actions),
 * l'erreur résiduelle reste très en deçà du centime.
 *
 * L'affichage n'est pas concerné : Montant::format() et les relevés PDF arrondissent
 * déjà le taux à l'unité.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE baremes_dividendes MODIFY COLUMN benefice_par_action DECIMAL(15,8) NOT NULL');
        DB::statement('ALTER TABLE dividendes MODIFY COLUMN benefice_par_action DECIMAL(15,8) NOT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE baremes_dividendes MODIFY COLUMN benefice_par_action DECIMAL(15,4) NOT NULL');
        DB::statement('ALTER TABLE dividendes MODIFY COLUMN benefice_par_action DECIMAL(15,4) NOT NULL');
    }
};
