<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Generalise l'offrande Waqf : elle peut honorer un defunt (« a la memoire de ») ou une
 * personne vivante a qui l'on fait cadeau (« au profit de »).
 *
 * Dans les deux cas le mecanisme est le meme — le donateur paie, les actions sont
 * comptabilisees au compte institutionnel Waqf Dolel Xamxam, et la personne honoree n'est
 * qu'une mention sans droit patrimonial. Seul le motif change. On a donc un seul dispositif
 * avec un type, plutot que deux flux paralleles.
 *
 * Les colonnes « en_memoire » sont renommees en « offrande_pour », leur nom devenant faux
 * des lors qu'elles peuvent designer un vivant. Renommage fait en SQL brut : doctrine/dbal
 * n'est pas installe sur ce projet.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE achats_actions CHANGE en_memoire_de offrande_pour VARCHAR(150) NULL");
        DB::statement("ALTER TABLE achats_actions CHANGE en_memoire_investisseur_id offrande_pour_investisseur_id BIGINT UNSIGNED NULL");
        DB::statement("ALTER TABLE achats_actions ADD COLUMN type_offrande ENUM('memoire','honneur') NULL AFTER offert_par_investisseur_id");

        // Les offrandes deja saisies etaient toutes des hommages a un defunt.
        DB::table('achats_actions')->whereNotNull('offert_par_investisseur_id')->update(['type_offrande' => 'memoire']);
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE achats_actions DROP COLUMN type_offrande");
        DB::statement("ALTER TABLE achats_actions CHANGE offrande_pour_investisseur_id en_memoire_investisseur_id BIGINT UNSIGNED NULL");
        DB::statement("ALTER TABLE achats_actions CHANGE offrande_pour en_memoire_de VARCHAR(150) NULL");
    }
};
