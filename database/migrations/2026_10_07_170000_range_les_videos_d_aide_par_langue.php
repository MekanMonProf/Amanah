<?php

use App\Support\Aide;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Une adresse de vidéo par couple sujet / langue, et non plus par sujet.
 *
 * Le mode d'emploi existe en trois langues ; ses vidéos n'avaient qu'un
 * emplacement. Un enregistrement arabe n'avait donc nulle part où aller, à
 * moins d'écraser le français. C'était un oubli de conception, pas un choix.
 *
 * La forme passe de `{sujet: adresse}` à `{sujet: {langue: adresse}}`. Les
 * adresses déjà posées rejoignent la langue source, celle dans laquelle les
 * pages ont été écrites : c'est la seule hypothèse raisonnable sur une vidéo
 * dont personne n'a jamais eu à déclarer la langue.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->convertir(fn (array $videos) => array_map(
            fn ($adresse) => is_string($adresse) ? [Aide::LANGUE_SOURCE => $adresse] : $adresse,
            $videos,
        ));
    }

    public function down(): void
    {
        // Le retour ne garde qu'une adresse par sujet : celle de la langue
        // source si elle existe, la première sinon. Les autres sont perdues,
        // et c'est inévitable — l'ancienne forme n'a pas de place pour elles.
        $this->convertir(fn (array $videos) => array_filter(array_map(
            fn ($par) => is_array($par)
                ? ($par[Aide::LANGUE_SOURCE] ?? (reset($par) ?: null))
                : $par,
            $videos,
        )));
    }

    private function convertir(callable $transformation): void
    {
        foreach (DB::table('parametres_societe')->get() as $ligne) {
            $videos = json_decode($ligne->videos ?? '[]', true);

            if (! is_array($videos) || $videos === []) {
                continue;
            }

            DB::table('parametres_societe')
                ->where('id', $ligne->id)
                ->update(['videos' => json_encode($transformation($videos))]);
        }
    }
};
