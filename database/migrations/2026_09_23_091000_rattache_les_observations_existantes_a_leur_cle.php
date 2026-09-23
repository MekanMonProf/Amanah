<?php

use App\Support\Observation;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Reprise de l'historique : rattache les observations déjà en base à leur clé.
 *
 * Aucune donnée n'est modifiée — la colonne `observations` garde sa phrase
 * française. On ne fait qu'écrire la clé et les paramètres à côté, pour que ces
 * lignes anciennes se traduisent comme les nouvelles. La reconnaissance vit dans
 * App\Support\Observation, qui sait seul quelles phrases l'application produit.
 */
return new class extends Migration
{
    private const TABLES = ['ecritures_compte_financier', 'achats_actions'];

    public function up(): void
    {
        $bilan = [];

        foreach (self::TABLES as $table) {
            $rattachees = 0;

            DB::table($table)
                ->whereNull('observation_cle')
                ->whereNotNull('observations')
                ->where('observations', '!=', '')
                ->orderBy('id')
                ->chunkById(200, function ($lignes) use ($table, &$rattachees) {
                    foreach ($lignes as $ligne) {
                        $trouvaille = Observation::reconnaitre($ligne->observations);

                        if ($trouvaille === null) {
                            continue;
                        }

                        [$cle, $parametres] = $trouvaille;

                        DB::table($table)->where('id', $ligne->id)->update([
                            'observation_cle' => $cle,
                            'observation_parametres' => json_encode($parametres, JSON_UNESCAPED_UNICODE),
                        ]);

                        $rattachees++;
                    }
                });

            $bilan[] = "$table : $rattachees";
        }

        echo '      observations rattachées — ' . implode(' | ', $bilan) . PHP_EOL;
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            DB::table($table)->update(['observation_cle' => null, 'observation_parametres' => null]);
        }
    }
};
