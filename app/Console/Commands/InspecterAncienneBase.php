<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Dresse l'inventaire de l'ancienne base, sans en lire le contenu.
 *
 * Avant d'écrire une reprise de données, il faut savoir ce qu'on reprend :
 * quelles tables existent, quelles colonnes elles portent, combien de lignes
 * elles contiennent. Cette commande répond à ça et s'arrête là.
 *
 * Elle n'affiche aucune valeur : ni nom, ni téléphone, ni montant. L'inventaire
 * se partage — il décrit une structure. Les données, non. C'est aussi ce qui
 * permet de me l'envoyer sans exporter de fichier contenant les investisseurs.
 *
 * La connexion « ancienne » est déclarée en lecture seule par convention : la
 * commande n'écrit rien, et aucune autre partie de l'application ne l'ouvre.
 */
class InspecterAncienneBase extends Command
{
    protected $signature = 'amanah:inspecter-ancienne-base
                            {--table= : Détailler une seule table}';

    protected $description = "Inventorie la structure de l'ancienne base, sans lire les données";

    /** Les tables qui signent l'ancienne application. */
    private const TEMOINS = ['utilisateurs', 'releves', 'antennes'];

    private string $connexion = 'ancienne';

    public function handle(): int
    {
        if (! $this->choisirConnexion()) {
            return self::FAILURE;
        }

        try {
            $tables = $this->tables();
        } catch (Throwable $e) {
            $this->error("Connexion impossible : " . $e->getMessage());

            return self::FAILURE;
        }

        if ($tables === []) {
            $this->warn("La base répond, mais ne contient aucune table.");

            return self::SUCCESS;
        }

        if ($nom = $this->option('table')) {
            return $this->detailler($nom, $tables);
        }

        $this->inventaire($tables);

        return self::SUCCESS;
    }

    /**
     * Ou sont les anciennes tables ?
     *
     * Deux cas, selon l'hebergement. Quand le forfait n'autorise qu'une seule
     * base, AMANAH pose ses tables a cote de celles qui existaient deja : les
     * anciennes sont alors dans la connexion ordinaire, et il n'y a rien a
     * configurer. Quand deux bases sont possibles, ANCIENNE_DB_DATABASE dit ou
     * chercher.
     *
     * On ne devine pas : on verifie que les tables temoins sont bien la, sinon
     * on le dit plutot que d'inventorier la mauvaise base.
     */
    private function choisirConnexion(): bool
    {
        if (filled(config('database.connections.ancienne.database'))) {
            $this->connexion = 'ancienne';
            $this->line("Connexion : base separee « " . $this->base() . " ».");

            return true;
        }

        $this->connexion = config('database.default');

        try {
            $presentes = array_filter(self::TEMOINS, fn ($t) => Schema::connection($this->connexion)->hasTable($t));
        } catch (Throwable $e) {
            $this->error("Connexion impossible : " . $e->getMessage());

            return false;
        }

        if ($presentes === []) {
            $this->error("Aucune ancienne table trouvee.");
            $this->line("Attendu dans la base « " . $this->base() . " » : " . implode(', ', self::TEMOINS) . ".");
            $this->newLine();
            $this->line("Si l'ancienne application vit dans une AUTRE base, renseignez");
            $this->line("ANCIENNE_DB_DATABASE, ANCIENNE_DB_USERNAME et ANCIENNE_DB_PASSWORD.");

            return false;
        }

        $this->line("Connexion : base partagee « " . $this->base() . " » ("
            . count($presentes) . "/" . count(self::TEMOINS) . " tables temoins presentes).");

        return true;
    }

    private function base(): string
    {
        return (string) config("database.connections.{$this->connexion}.database");
    }

    /**
     * Les tables qu'AMANAH s'est creees, lues dans ses migrations.
     *
     * Dans une base partagee, l'inventaire doit dire a qui appartient chaque
     * table, sinon il melange les deux applications et ne sert a rien. Plutot
     * qu'une liste ecrite a la main qui vieillirait mal, on relit les
     * migrations : elles sont la source de verite, et elles suivent le code.
     *
     * @return list<string>
     */
    private function tablesAmanah(): array
    {
        // Laravel cree lui-meme son journal de migrations, sans passer par une
        // migration : aucune ne le declare, et sans cette ligne il passerait
        // pour une table de l'ancienne application.
        $noms = ['migrations'];

        foreach (glob(database_path('migrations/*.php')) ?: [] as $fichier) {
            if (preg_match_all(
                "/Schema::(?:create|createIfNotExists)\(\s*['\"]([a-zA-Z0-9_]+)['\"]/",
                (string) file_get_contents($fichier), $trouves,
            )) {
                $noms = array_merge($noms, $trouves[1]);
            }
        }

        return array_values(array_unique($noms));
    }

    /** @return list<string> */
    private function tables(): array
    {
        $base = $this->base();

        return collect(DB::connection($this->connexion)->select(
            'SELECT TABLE_NAME AS nom FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = ? ORDER BY TABLE_NAME', [$base],
        ))->pluck('nom')->all();
    }

    private function inventaire(array $tables): void
    {
        $this->info("Base « " . $this->base() . " » : "
            . count($tables) . " table(s)");
        $this->newLine();

        $amanah = $this->tablesAmanah();
        $lignes = [];
        $compte = ['ancienne' => 0, 'AMANAH' => 0];

        foreach ($tables as $table) {
            $colonnes = $this->colonnes($table);
            $origine = in_array($table, $amanah, true) ? 'AMANAH' : 'ancienne';
            $compte[$origine]++;

            $lignes[] = [
                $origine,
                $table,
                number_format($this->compter($table), 0, ',', ' '),
                count($colonnes),
                // Les premiers noms de colonnes suffisent le plus souvent à
                // reconnaître une table ; le détail se demande table par table.
                implode(', ', array_slice($colonnes, 0, 5)) . (count($colonnes) > 5 ? ', …' : ''),
            ];
        }

        // Les anciennes d'abord : ce sont elles qu'on vient inventorier.
        usort($lignes, fn ($a, $b) => [$a[0] === 'AMANAH', $a[1]] <=> [$b[0] === 'AMANAH', $b[1]]);

        $this->table(['Origine', 'Table', 'Lignes', 'Col.', 'Premières colonnes'], $lignes);
        $this->line(sprintf(
            "  %d table(s) de l'ancienne application, %d d'AMANAH — aucun nom en commun.",
            $compte['ancienne'], $compte['AMANAH'],
        ));
        $this->newLine();
        $this->line("Pour le détail d'une table :");
        $this->line("  php artisan amanah:inspecter-ancienne-base --table=nom_de_la_table");
    }

    private function detailler(string $nom, array $tables): int
    {
        if (! in_array($nom, $tables, true)) {
            $this->error("Table « {$nom} » introuvable.");
            $this->line("Tables disponibles : " . implode(', ', $tables));

            return self::FAILURE;
        }

        $base = $this->base();

        $this->info("Table « {$nom} » — " . number_format($this->compter($nom), 0, ',', ' ') . " ligne(s)");
        $this->newLine();

        $colonnes = DB::connection($this->connexion)->select(
            'SELECT COLUMN_NAME AS nom, COLUMN_TYPE AS type, IS_NULLABLE AS nullable,
                    COLUMN_KEY AS cle, COLUMN_DEFAULT AS defaut
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION', [$base, $nom],
        );

        $this->table(
            ['Colonne', 'Type', 'Nulle ?', 'Clé', 'Défaut'],
            collect($colonnes)->map(fn ($c) => [
                $c->nom, $c->type, $c->nullable === 'YES' ? 'oui' : 'non',
                $c->cle ?: '', $c->defaut ?? '',
            ])->all(),
        );

        return self::SUCCESS;
    }

    /** @return list<string> */
    private function colonnes(string $table): array
    {
        return collect(DB::connection($this->connexion)->select(
            'SELECT COLUMN_NAME AS nom FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION',
            [$this->base(), $table],
        ))->pluck('nom')->all();
    }

    private function compter(string $table): int
    {
        try {
            return (int) DB::connection($this->connexion)->table($table)->count();
        } catch (Throwable) {
            return 0;
        }
    }
}
