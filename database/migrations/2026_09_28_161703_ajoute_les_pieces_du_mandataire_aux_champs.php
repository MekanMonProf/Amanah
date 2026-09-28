<?php

use App\Support\Completude;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Les trois pièces du mandataire rejoignent les champs réglables.
 *
 * Elles étaient exigées à la désignation : le formulaire refusait d'enregistrer
 * sans elles, seul endroit de la plateforme à bloquer pour un papier manquant.
 * Or une succession s'ouvre souvent avant que la famille n'ait réuni ses pièces,
 * et refuser la désignation retardait tout le reste du règlement.
 *
 * Elles restent réclamées par défaut, mais comme signalement : le dossier apparaît
 * incomplet tant qu'elles manquent, sans que rien ne soit bloqué.
 */
return new class extends Migration
{
    public function up(): void
    {
        $maintenant = now();

        foreach (array_keys(Completude::CATALOGUE_SUCCESSION) as $champ) {
            DB::table('champs_dossier')->updateOrInsert(
                ['contexte' => 'succession', 'champ' => $champ],
                [
                    'actif' => in_array($champ, Completude::DEFAUTS_SUCCESSION, true),
                    'created_at' => $maintenant,
                    'updated_at' => $maintenant,
                ],
            );
        }
    }

    public function down(): void
    {
        DB::table('champs_dossier')
            ->where('contexte', 'succession')
            ->where('champ', 'like', Completude::PREFIXE_MANDATAIRE . '%')
            ->delete();
    }
};
