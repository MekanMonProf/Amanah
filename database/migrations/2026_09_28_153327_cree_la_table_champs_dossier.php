<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ce qu'un dossier doit contenir pour être dit complet, une ligne par champ.
 *
 * La table porte tout le catalogue, coché ou non, plutôt que les seules lignes
 * retenues : l'écran de paramétrage affiche ainsi ce qu'on peut réclamer autant
 * que ce qu'on réclame, et décocher un champ ne fait pas disparaître la ligne.
 *
 * Les champs cochés au départ sont exactement ceux qui étaient exigés avant que
 * l'écran n'existe : le compte des dossiers incomplets ne bouge pas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('champs_dossier', function (Blueprint $table) {
            $table->id();
            $table->enum('type_personne', ['physique', 'morale']);
            $table->string('champ', 60);
            $table->boolean('actif')->default(false);
            $table->timestamps();

            $table->unique(['type_personne', 'champ']);
        });

        /*
         * Les listes sont recopiees ici, et non lues dans App\Support\Completude.
         *
         * Elles l'etaient : la migration appelait Completude::catalogue(). Le
         * catalogue s'est enrichi ensuite, de trois pieces jointes, et comme une
         * migration relit le code du jour ou on la rejoue et non celui du jour ou
         * on l'a ecrite, une installation neuve inserait deja ces trois champs —
         * puis la migration suivante, qui croyait les ajouter, se heurtait a la
         * contrainte d'unicite. L'installation s'arretait la. Rien ne se voyait
         * sur une base existante, ou cette migration etait passee avant.
         *
         * Une migration decrit un etat passe. Elle ne doit donc dependre d'aucun
         * code susceptible de changer apres elle.
         */
        $catalogues = [
            'physique' => [
                'type_identification', 'numero_identification', 'piece_identite_path',
                'date_delivrance_piece', 'lieu_delivrance_piece', 'date_expiration_piece',
                'date_naissance', 'lieu_naissance', 'nationalite', 'adresse', 'ville',
                'pays', 'email', 'whatsapp', 'convention_engagement_path',
                'date_signature_convention', 'beneficiaire_nom', 'beneficiaire_telephone',
            ],
            'morale' => [
                'rccm', 'ninea', 'adresse', 'ville', 'pays', 'email',
                'representant_legal_nom', 'representant_legal_telephone',
                'convention_engagement_path', 'date_signature_convention',
            ],
        ];

        // Ce qui etait reclame avant que l'ecran de reglage n'existe : la mise a
        // jour ne change le compte des dossiers incomplets pour personne.
        $defauts = [
            'physique' => [
                'type_identification', 'numero_identification', 'piece_identite_path',
                'date_naissance', 'lieu_naissance', 'nationalite', 'adresse', 'pays',
                'convention_engagement_path',
            ],
            'morale' => ['rccm', 'ninea', 'adresse', 'pays', 'convention_engagement_path'],
        ];

        $maintenant = now();
        $lignes = [];

        foreach ($catalogues as $type => $champs) {
            foreach ($champs as $champ) {
                $lignes[] = [
                    'type_personne' => $type,
                    'champ' => $champ,
                    'actif' => in_array($champ, $defauts[$type], true),
                    'created_at' => $maintenant,
                    'updated_at' => $maintenant,
                ];
            }
        }

        DB::table('champs_dossier')->insert($lignes);
    }

    public function down(): void
    {
        Schema::dropIfExists('champs_dossier');
    }
};
