<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Retire un réglage qui ne réglait rien.
 *
 * `reinvestissement_par_defaut` existait depuis l'origine sur les politiques,
 * mais aucun code ne l'a jamais lue : compteOuCree() pose `reinvestissement_auto`
 * à vrai pour les deux catégories, sans la consulter. Une colonne qu'on croit
 * suivie et qui ne l'est pas est pire qu'une absence : elle fait croire à un
 * réglage, et le jour où quelqu'un la passe à faux, rien ne change et personne
 * ne comprend pourquoi.
 *
 * Le comportement ne bouge pas : un compte neuf continue de naître avec le
 * réinvestissement automatique, et il se coupe compte par compte depuis la fiche
 * de l'investisseur, là où la décision se prend vraiment.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('politiques_investissement', function (Blueprint $table) {
            $table->dropColumn('reinvestissement_par_defaut');
        });
    }

    public function down(): void
    {
        Schema::table('politiques_investissement', function (Blueprint $table) {
            $table->boolean('reinvestissement_par_defaut')->default(true)->after('eligible_dividendes');
        });
    }
};
