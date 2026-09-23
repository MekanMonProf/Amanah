<?php

use App\Support\Observation;
use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Reprise de l'historique : rattache les observations déjà en base à leur clé.
 *
 * Aucune donnée n'est modifiée — la colonne `observations` garde sa phrase
 * française. On ne fait qu'écrire la clé et les paramètres à côté, pour que ces
 * lignes anciennes se traduisent comme les nouvelles.
 *
 * Le rattachement n'a lieu que si la phrase reconstruite à partir de la clé est
 * identique, caractère pour caractère, à celle qui est stockée. Une ligne qui ne
 * se reconstruit pas exactement est laissée telle quelle : mieux vaut une
 * observation non traduite qu'une observation mal étiquetée dans un historique
 * financier.
 */
return new class extends Migration
{
    private const TABLES = ['ecritures_compte_financier', 'achats_actions'];

    public function up(): void
    {
        $motifs = $this->motifs();
        $bilan = [];

        foreach (self::TABLES as $table) {
            $rattachees = 0;

            DB::table($table)
                ->whereNull('observation_cle')
                ->whereNotNull('observations')
                ->where('observations', '!=', '')
                ->orderBy('id')
                ->chunkById(200, function ($lignes) use ($table, $motifs, &$rattachees) {
                    foreach ($lignes as $ligne) {
                        $trouvaille = $this->reconnaitre($ligne->observations, $motifs);

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

        // Trace dans la sortie de migrate : le compte doit pouvoir être vérifié.
        echo '      observations rattachées — ' . implode(' | ', $bilan) . PHP_EOL;
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            DB::table($table)->update(['observation_cle' => null, 'observation_parametres' => null]);
        }
    }

    /**
     * Une expression régulière par clé connue, jetons transformés en groupes
     * nommés. Les clés portant le plus de jetons passent en premier : sans cela
     * « Complément financier — Wave (réf. X) » serait happé par la variante sans
     * référence, avec le mode « Wave (réf. X) ».
     *
     * @return array<string, string>
     */
    private function motifs(): array
    {
        $cles = Observation::cles();

        usort($cles, fn ($a, $b) => [substr_count($b, ':'), strlen($b)] <=> [substr_count($a, ':'), strlen($a)]);

        $motifs = [];

        foreach ($cles as $cle) {
            // preg_quote echappe aussi les deux-points : appliquer le remplacement
            // apres coup collerait un antislash devant le groupe et casserait le
            // motif. On pose donc des sentinelles avant d echapper.
            $sentinelles = [];

            $avecSentinelles = preg_replace_callback(
                '/:([a-z_]+)/',
                function ($trouve) use (&$sentinelles) {
                    $marque = "" . count($sentinelles) . "";
                    $sentinelles[$marque] = $trouve[1];

                    return $marque;
                },
                $cle,
            );

            $motif = preg_quote(self::normaliser($avecSentinelles), '/');

            foreach ($sentinelles as $marque => $nom) {
                $motif = str_replace($marque, "(?P<{$nom}>.+?)", $motif);
            }

            $motifs[$cle] = '/^' . $motif . '$/us';
        }

        return $motifs;
    }

    /**
     * @return array{0: string, 1: array}|null
     */
    private function reconnaitre(string $phrase, array $motifs): ?array
    {
        $normalisee = self::normaliser($phrase);

        foreach ($motifs as $cle => $motif) {
            if (! preg_match($motif, $normalisee, $captures)) {
                continue;
            }

            $parametres = $this->versValeursBrutes($captures);

            if ($parametres === null) {
                continue;
            }

            // Garde-fou : on n'étiquette que ce qui se reconstruit à l'identique,
            // à la largeur des espaces près. Les observations écrites avant
            // App\Support\Montant séparent les milliers par une espace ordinaire,
            // là où la clé pose aujourd'hui une espace fine insécable. Rien d'autre
            // n'est toléré, et le texte stocké n'est de toute façon pas réécrit.
            if (self::normaliser(Observation::francais($cle, $parametres)) !== $normalisee) {
                continue;
            }

            return [$cle, $parametres];
        }

        return null;
    }

    /**
     * Remonte du texte affiché vers la valeur brute attendue par la clé — une
     * date ISO pour la période, la valeur d'énumération pour la catégorie, un
     * nombre pour les montants. Null si l'un d'eux ne se relit pas.
     */
    private function versValeursBrutes(array $captures): ?array
    {
        $bruts = [];

        foreach ($captures as $nom => $valeur) {
            if (is_int($nom)) {
                continue;
            }

            switch ($nom) {
                case 'periode':
                    $date = $this->relireMois($valeur);
                    if ($date === null) {
                        return null;
                    }
                    $bruts[$nom] = $date;
                    break;

                case 'categorie':
                    $code = $this->relireCategorie($valeur);
                    if ($code === null) {
                        return null;
                    }
                    $bruts[$nom] = $code;
                    break;

                case 'ancien':
                case 'nouveau':
                case 'actions':
                    $nombre = str_replace([' ', "\u{202F}", "\u{00A0}"], '', $valeur);
                    if (! is_numeric($nombre)) {
                        return null;
                    }
                    $bruts[$nom] = $nombre + 0;
                    break;

                default:
                    $bruts[$nom] = $valeur;
            }
        }

        return $bruts;
    }

    /** Ramène les espaces fines et insécables à l'espace ordinaire. */
    private static function normaliser(string $texte): string
    {
        return str_replace(["\u{202F}", "\u{00A0}"], ' ', $texte);
    }

    private function relireMois(string $libelle): ?string
    {
        try {
            return Carbon::createFromLocaleFormat('!F Y', 'fr', $libelle)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function relireCategorie(string $libelle): ?string
    {
        foreach (['commercial', 'waqf'] as $code) {
            if (\App\Support\Libelles::categorie($code) === $libelle) {
                return $code;
            }
        }

        return null;
    }
};
