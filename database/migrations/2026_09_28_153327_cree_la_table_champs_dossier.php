<?php

use App\Support\Completude;
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

        $maintenant = now();
        $lignes = [];

        foreach (['physique', 'morale'] as $type) {
            $defauts = Completude::defauts($type);

            foreach (array_keys(Completude::catalogue($type)) as $champ) {
                $lignes[] = [
                    'type_personne' => $type,
                    'champ' => $champ,
                    'actif' => in_array($champ, $defauts, true),
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
