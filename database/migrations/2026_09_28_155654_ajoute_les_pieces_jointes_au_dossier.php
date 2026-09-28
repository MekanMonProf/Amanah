<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Les pièces qui se joignent au dossier, et le contexte qui décide de les réclamer.
 *
 * Le dossier ne portait que deux documents : la pièce d'identité et la convention
 * d'engagement. L'acte de décès existait déjà mais nulle part on ne pouvait
 * demander qu'il soit fourni ; la procuration n'existait que pour le mandataire
 * d'une succession, pas pour un investisseur qui se fait représenter de son
 * vivant ; le justificatif de domicile et le RIB, réclamés partout ailleurs dans
 * la finance, n'avaient aucune place.
 *
 * `champs_dossier.type_personne` devient `contexte` : « succession » n'est pas un
 * type de personne, c'est une situation. Un dossier de défunt se voit réclamer
 * les champs de son type ET ceux de la succession ; les autres ignorent ces
 * derniers, sans quoi trente-sept dossiers vivants seraient signalés incomplets
 * faute d'un acte de décès.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('investisseurs', function (Blueprint $table) {
            $table->string('piece_procuration_path')->nullable()->after('convention_engagement_path');
            $table->string('piece_justificatif_domicile_path')->nullable()->after('piece_procuration_path');
            $table->string('piece_rib_path')->nullable()->after('piece_justificatif_domicile_path');
        });

        Schema::table('champs_dossier', function (Blueprint $table) {
            $table->renameColumn('type_personne', 'contexte');
        });

        DB::statement("ALTER TABLE champs_dossier MODIFY contexte ENUM('physique','morale','succession') NOT NULL");

        // L'acte de décès est la seule pièce que la succession réclame d'emblée :
        // c'est elle qui ouvre le dossier, et la plateforme la demande déjà à la
        // déclaration. Les autres pièces de succession vivent sur l'héritier.
        $maintenant = now();
        $lignes = [
            ['contexte' => 'succession', 'champ' => 'piece_acte_deces_path', 'actif' => true],
        ];

        foreach (['physique', 'morale'] as $type) {
            foreach (['piece_procuration_path', 'piece_justificatif_domicile_path', 'piece_rib_path'] as $champ) {
                $lignes[] = ['contexte' => $type, 'champ' => $champ, 'actif' => false];
            }
        }

        DB::table('champs_dossier')->insert(array_map(
            fn ($ligne) => $ligne + ['created_at' => $maintenant, 'updated_at' => $maintenant],
            $lignes,
        ));
    }

    public function down(): void
    {
        DB::table('champs_dossier')->whereIn('champ', [
            'piece_acte_deces_path', 'piece_procuration_path',
            'piece_justificatif_domicile_path', 'piece_rib_path',
        ])->delete();

        DB::statement("ALTER TABLE champs_dossier MODIFY contexte ENUM('physique','morale') NOT NULL");

        Schema::table('champs_dossier', function (Blueprint $table) {
            $table->renameColumn('contexte', 'type_personne');
        });

        Schema::table('investisseurs', function (Blueprint $table) {
            $table->dropColumn([
                'piece_procuration_path',
                'piece_justificatif_domicile_path',
                'piece_rib_path',
            ]);
        });
    }
};
