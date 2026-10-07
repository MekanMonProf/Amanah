<?php

/**
 * Construit la base de démonstration, séparée de celle qui porte les vrais
 * dossiers.
 *
 * Elle sert à enregistrer les vidéos du mode d'emploi et à montrer
 * l'application sans exposer un nom, un numéro de pièce d'identité ou une
 * position financière réelle. Une vidéo échappe à son auteur : ce qui y figure
 * y figure pour toujours.
 *
 * Le script refuse de travailler sur la base déclarée dans le `.env`.
 * PresentationSeeder commence par vider les tables métier — le lancer sur la
 * mauvaise base effacerait le travail de reprise du classeur. Ce garde-fou est
 * la raison d'être de ce fichier : les trois commandes qu'il enchaîne
 * pourraient se taper à la main, mais pas cette vérification.
 *
 * Usage : php database/demo/preparer.php [nom-de-la-base]
 *         (par défaut : amanah_demo)
 */

$racine = dirname(__DIR__, 2);

require $racine . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable($racine);
$dotenv->safeLoad();

$cible = $argv[1] ?? 'amanah_demo';
$reelle = $_ENV['DB_DATABASE'] ?? null;
$hote = $_ENV['DB_HOST'] ?? '127.0.0.1';
$port = $_ENV['DB_PORT'] ?? '3306';
$utilisateur = $_ENV['DB_USERNAME'] ?? 'root';
$motDePasse = $_ENV['DB_PASSWORD'] ?? '';

echo "Base de l'application : {$reelle}\n";
echo "Base de démonstration : {$cible}\n\n";

if ($cible === $reelle) {
    echo "ABANDON : la base de démonstration porterait le nom de celle de l'application.\n";
    echo "Le jeu de démonstration commence par vider les tables métier.\n";
    exit(1);
}

if (! str_contains($cible, 'demo')) {
    echo "ABANDON : par prudence, le nom de la base de démonstration doit contenir « demo ».\n";
    exit(1);
}

try {
    $pdo = new PDO(
        "mysql:host={$hote};port={$port}",
        $utilisateur,
        $motDePasse,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
    );
} catch (PDOException $e) {
    echo "ABANDON : impossible de joindre MySQL — {$e->getMessage()}\n";
    exit(1);
}

$existe = $pdo->query("SHOW DATABASES LIKE " . $pdo->quote($cible))->fetchColumn() !== false;

if ($existe) {
    echo "La base « {$cible} » existe déjà : elle va être vidée et refaite.\n";
    echo "Tapez « oui » pour continuer : ";

    if (trim((string) fgets(STDIN)) !== 'oui') {
        echo "Rien n'a été fait.\n";
        exit;
    }

    $pdo->exec("DROP DATABASE `{$cible}`");
}

$pdo->exec("CREATE DATABASE `{$cible}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
echo "Base « {$cible} » créée.\n\n";

/**
 * Les commandes tournent sur la base cible sans toucher au `.env` : Dotenv ne
 * remplace pas une variable déjà posée dans l'environnement, c'est donc celle
 * du processus qui gagne.
 */
$php = PHP_BINARY;
$etapes = [
    'Migrations' => 'migrate --force',
    'Paramètres initiaux' => 'db:seed --class=ParametresInitiauxSeeder --force',
    'Jeu de démonstration' => 'db:seed --class=PresentationSeeder --force',
];

foreach ($etapes as $libelle => $commande) {
    echo "— {$libelle}\n";

    $ligne = sprintf(
        '%s "%s" %s',
        escapeshellarg($php),
        $racine . '/artisan',
        $commande,
    );

    // getenv() et non $_ENV : sous Windows, $_ENV peut être partiel selon
    // variables_order, et un processus fils privé de SystemRoot ne sait plus
    // ouvrir une socket — l'erreur qu'on obtient alors ne parle pas de ça.
    $environnement = array_merge(getenv(), $_ENV, ['DB_DATABASE' => $cible]);

    $processus = proc_open(
        $ligne,
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $tuyaux,
        $racine,
        $environnement,
    );

    if (! is_resource($processus)) {
        echo "ABANDON : impossible de lancer « {$commande} ».\n";
        exit(1);
    }

    echo stream_get_contents($tuyaux[1]);
    $erreurs = stream_get_contents($tuyaux[2]);

    fclose($tuyaux[1]);
    fclose($tuyaux[2]);

    if (proc_close($processus) !== 0) {
        echo $erreurs;
        echo "\nABANDON à l'étape « {$libelle} ».\n";
        exit(1);
    }
}

echo "\nLa base de démonstration est prête.\n";
echo "Pour basculer dessus : php database/demo/basculer.php demo\n";
