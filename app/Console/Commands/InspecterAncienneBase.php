<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
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

    public function handle(): int
    {
        if (blank(config('database.connections.ancienne.database'))) {
            $this->error("Aucune ancienne base configurée.");
            $this->line("Renseignez ANCIENNE_DB_DATABASE, ANCIENNE_DB_USERNAME et");
            $this->line("ANCIENNE_DB_PASSWORD dans le fichier .env, puis relancez.");

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

    /** @return list<string> */
    private function tables(): array
    {
        $base = config('database.connections.ancienne.database');

        return collect(DB::connection('ancienne')->select(
            'SELECT TABLE_NAME AS nom FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = ? ORDER BY TABLE_NAME', [$base],
        ))->pluck('nom')->all();
    }

    private function inventaire(array $tables): void
    {
        $this->info("Base « " . config('database.connections.ancienne.database') . " » : "
            . count($tables) . " table(s)");
        $this->newLine();

        $lignes = [];

        foreach ($tables as $table) {
            $colonnes = $this->colonnes($table);

            $lignes[] = [
                $table,
                number_format($this->compter($table), 0, ',', ' '),
                count($colonnes),
                // Les premiers noms de colonnes suffisent le plus souvent à
                // reconnaître une table ; le détail se demande table par table.
                implode(', ', array_slice($colonnes, 0, 6)) . (count($colonnes) > 6 ? ', …' : ''),
            ];
        }

        $this->table(['Table', 'Lignes', 'Col.', 'Premières colonnes'], $lignes);
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

        $base = config('database.connections.ancienne.database');

        $this->info("Table « {$nom} » — " . number_format($this->compter($nom), 0, ',', ' ') . " ligne(s)");
        $this->newLine();

        $colonnes = DB::connection('ancienne')->select(
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
        return collect(DB::connection('ancienne')->select(
            'SELECT COLUMN_NAME AS nom FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION',
            [config('database.connections.ancienne.database'), $table],
        ))->pluck('nom')->all();
    }

    private function compter(string $table): int
    {
        try {
            return (int) DB::connection('ancienne')->table($table)->count();
        } catch (Throwable) {
            return 0;
        }
    }
}
