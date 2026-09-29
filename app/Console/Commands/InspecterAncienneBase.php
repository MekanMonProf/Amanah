<?php

namespace App\Console\Commands;

use App\Support\AncienneBase;
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

    private function choisirConnexion(): bool
    {
        $manquantes = [];
        $connexion = AncienneBase::connexion($manquantes);

        if ($connexion === null) {
            $this->error('Aucune ancienne table trouvee.');
            $this->line('Attendu dans la base « ' . AncienneBase::base(AncienneBase::connexionEssayee())
                . ' » : ' . implode(', ', $manquantes) . '.');
            $this->newLine();
            $this->line("Si l'ancienne application vit dans une AUTRE base, renseignez");
            $this->line('ANCIENNE_DB_DATABASE, ANCIENNE_DB_USERNAME et ANCIENNE_DB_PASSWORD.');

            return false;
        }

        $this->connexion = $connexion;
        $this->line(AncienneBase::estSeparee()
            ? 'Connexion : base separee « ' . $this->base() . ' ».'
            : 'Connexion : base partagee « ' . $this->base() . ' ».');

        return true;
    }

    private function base(): string
    {
        return AncienneBase::base($this->connexion);
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
