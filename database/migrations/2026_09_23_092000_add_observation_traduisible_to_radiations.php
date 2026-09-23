<?php

use App\Support\Observation;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Même traitement que pour les écritures et les achats, appliqué aux radiations.
 *
 * Une seule phrase de cette table vient de l'application : celle posée lors de
 * la liquidation d'une succession. Les autres observations sont saisies au
 * moment de la radiation et restent dans la langue où elles ont été écrites.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('radiations', function (Blueprint $t) {
            $t->string('observation_cle', 255)->nullable()->after('observations');
            $t->json('observation_parametres')->nullable()->after('observation_cle');
        });

        $rattachees = 0;

        DB::table('radiations')
            ->whereNotNull('observations')
            ->where('observations', '!=', '')
            ->orderBy('id')
            ->chunkById(200, function ($lignes) use (&$rattachees) {
                foreach ($lignes as $ligne) {
                    $trouvaille = Observation::reconnaitre($ligne->observations);

                    if ($trouvaille === null) {
                        continue;
                    }

                    [$cle, $parametres] = $trouvaille;

                    DB::table('radiations')->where('id', $ligne->id)->update([
                        'observation_cle' => $cle,
                        'observation_parametres' => json_encode($parametres, JSON_UNESCAPED_UNICODE),
                    ]);

                    $rattachees++;
                }
            });

        echo "      radiations rattachées — $rattachees" . PHP_EOL;
    }

    public function down(): void
    {
        Schema::table('radiations', function (Blueprint $t) {
            $t->dropColumn(['observation_cle', 'observation_parametres']);
        });
    }
};
