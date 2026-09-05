<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * « Offrande » avait une resonance trop liturgique pour un document remis a un donateur.
 * Le terme retenu est « present » : il convient aussi bien a un hommage rendu a un defunt
 * qu a un cadeau fait a une personne vivante.
 *
 * Renommage en SQL brut, doctrine/dbal n etant pas installe. Aucune donnee a migrer :
 * aucun present n a encore ete saisi.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE achats_actions CHANGE type_offrande type_present ENUM('memoire','honneur') NULL");
        DB::statement("ALTER TABLE achats_actions CHANGE offrande_pour present_pour VARCHAR(150) NULL");
        DB::statement("ALTER TABLE achats_actions CHANGE offrande_pour_investisseur_id present_pour_investisseur_id BIGINT UNSIGNED NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE achats_actions CHANGE present_pour_investisseur_id offrande_pour_investisseur_id BIGINT UNSIGNED NULL");
        DB::statement("ALTER TABLE achats_actions CHANGE present_pour offrande_pour VARCHAR(150) NULL");
        DB::statement("ALTER TABLE achats_actions CHANGE type_present type_offrande ENUM('memoire','honneur') NULL");
    }
};
