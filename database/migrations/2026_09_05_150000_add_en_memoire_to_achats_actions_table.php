<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Achat Waqf offert à la mémoire d'un défunt.
 *
 * Les actions ne vont pas au compte du donateur : elles sont versées directement au
 * compte institutionnel « Waqf Dolel Xamxam » (le même que celui qui reçoit le capital
 * Waqf des successions, par inaliénabilité). L'achat porte donc trois informations
 * supplémentaires :
 *
 *  - offert_par_investisseur_id : le donateur, qui paie. C'est ce champ qui marque
 *    l'achat comme « à la mémoire de » — il est nul pour tout achat ordinaire.
 *  - en_memoire_de : le nom du défunt honoré, toujours renseigné, pour que l'attestation
 *    et les listes restent lisibles sans jointure.
 *  - en_memoire_investisseur_id : renseigné uniquement quand le défunt est un investisseur
 *    de la plateforme déclaré décédé ; nul quand c'est une personne extérieure.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('achats_actions', function (Blueprint $table) {
            $table->foreignId('offert_par_investisseur_id')->nullable()->after('saisi_par')
                ->constrained('investisseurs')->nullOnDelete();
            $table->string('en_memoire_de', 150)->nullable()->after('offert_par_investisseur_id');
            $table->foreignId('en_memoire_investisseur_id')->nullable()->after('en_memoire_de')
                ->constrained('investisseurs')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('achats_actions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('offert_par_investisseur_id');
            $table->dropConstrainedForeignId('en_memoire_investisseur_id');
            $table->dropColumn('en_memoire_de');
        });
    }
};
