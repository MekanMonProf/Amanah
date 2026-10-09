<?php

/**
 * Exporte les seules tables d'AMANAH, pour les remonter dans la base en ligne.
 *
 * En ligne, AMANAH et l'ancienne application partagent une même base. Un export
 * complet de la base locale écraserait donc aussi les quatorze tables de
 * l'ancienne application — qui tourne toujours, et dont les données en ligne
 * sont plus récentes que la copie locale. Ce script ne sort que ce qui
 * appartient à AMANAH, et nomme explicitement ce qu'il laisse.
 *
 * Il ne touche à rien : il écrit un fichier .sql, c'est tout. L'import se fait
 * à la main, par phpMyAdmin, après une sauvegarde de la base en ligne.
 *
 * Usage : php deploiement/exporter-tables-amanah.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

/**
 * Les tables de l'ancienne application. AMANAH les lit — la commande de reprise
 * et le détecteur AncienneBase — mais n'y écrit jamais. Elles restent donc en
 * ligne telles qu'elles sont.
 */
const ANCIENNE_APPLICATION = [
    'antennes', 'contrats_partenaires', 'fichiers_utilisateurs', 'historique_suppressions',
    'logs_actions', 'partenariat_apports', 'partenariat_financier', 'partenariats',
    'releves', 'roles', 'souscription_details', 'souscriptions', 'utilisateurs', 'versements',
];

/**
 * Ce qui ne survit pas à un déménagement et n'a pas à le faire : sessions
 * ouvertes sur une autre machine, cache, files d'attente, jetons de
 * réinitialisation. Les emporter remplacerait des données vivantes en ligne
 * par des données mortes d'ici.
 */
const RUNTIME = [
    'cache', 'cache_locks', 'failed_jobs', 'job_batches', 'jobs',
    'password_reset_tokens', 'sessions',
];

$base = config('database.connections.mysql.database');
$toutes = array_map(fn ($l) => array_values((array) $l)[0], DB::select('SHOW TABLES'));
sort($toutes);

$aExporter = array_values(array_diff($toutes, ANCIENNE_APPLICATION, RUNTIME));

echo "Base locale      : {$base}\n";
echo "Tables exportées : " . count($aExporter) . "\n";
echo "Laissées en ligne: " . count(ANCIENNE_APPLICATION) . " de l'ancienne application, "
    . count(array_intersect($toutes, RUNTIME)) . " de fonctionnement\n\n";

/**
 * Les comptes dont le mot de passe est écrit dans le dépôt — public — ne
 * doivent pas arriver en ligne tels quels : n'importe qui lisant GitHub aurait
 * un accès à la plateforme.
 *
 * On ne les supprime pas. `audit_logs.user_id` est en ON DELETE RESTRICT, et
 * surtout l'un d'eux n'est un compte d'essai que par son adresse : il porte le
 * profil d'une gestionnaire qui suit 183 dossiers. Le retirer la priverait
 * d'accès et afficherait « Compte de connexion supprimé » sur son profil.
 *
 * On remplace donc leur mot de passe par un aléatoire que personne ne connaît,
 * et on exige qu'il soit changé. Le compte purement démonstratif est de plus
 * désactivé : la connexion refuse un compte inactif.
 */
$neutraliser = function (array $ligne): array {
    $email = (string) ($ligne['email'] ?? '');

    if (! str_ends_with($email, '.test') && ! str_contains($email, 'example')) {
        return $ligne;
    }

    $ligne['password'] = password_hash(bin2hex(random_bytes(32)), PASSWORD_BCRYPT);
    $ligne['doit_changer_mot_de_passe'] = 1;

    if ($email === 'essai@local.test') {
        $ligne['actif'] = 0;
    }

    echo "  neutralisé : {$email}" . ($email === 'essai@local.test' ? ' (désactivé)' : ' (mot de passe à refaire)') . "\n";

    return $ligne;
};

$sortie = __DIR__ . '/sortie/amanah-tables-' . date('Ymd-Hi') . '.sql';
@mkdir(dirname($sortie), 0777, true);
$f = fopen($sortie, 'w');

fwrite($f, "-- Tables d'AMANAH exportées depuis {$base} le " . date('d/m/Y H:i') . "\n");
fwrite($f, "-- À importer dans waqfdole_gestionactionnaires_bd par phpMyAdmin.\n");
fwrite($f, "-- Les tables de l'ancienne application ne sont PAS dans ce fichier :\n");
fwrite($f, '--   ' . implode(', ', ANCIENNE_APPLICATION) . "\n\n");

// Les clés étrangères s'entrecroisent : on ne peut pas poser les tables dans un
// ordre qui les satisfasse toutes, et l'ordre alphabétique n'en satisfait aucun.
fwrite($f, "SET NAMES utf8mb4;\n");
fwrite($f, "SET FOREIGN_KEY_CHECKS = 0;\n");
fwrite($f, "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n\n");

$total = 0;

foreach ($aExporter as $table) {
    $creation = (array) DB::select("SHOW CREATE TABLE `{$table}`")[0];
    $sql = $creation['Create Table'] ?? array_values($creation)[1];

    fwrite($f, "-- ----------------------------------------------------------\n");
    fwrite($f, "DROP TABLE IF EXISTS `{$table}`;\n{$sql};\n");

    $lignes = DB::table($table)->get();
    $total += $lignes->count();

    if ($lignes->isNotEmpty()) {
        $colonnes = array_keys((array) $lignes->first());
        $entete = 'INSERT INTO `' . $table . '` (`' . implode('`, `', $colonnes) . '`) VALUES';

        // Par paquets : une seule instruction de sept mille lignes dépasse
        // max_allowed_packet sur un hébergement mutualisé.
        foreach ($lignes->chunk(200) as $paquet) {
            $valeurs = [];

            foreach ($paquet as $ligne) {
                $ligne = (array) $ligne;

                if ($table === 'users') {
                    $ligne = $neutraliser($ligne);
                }

                $cellules = array_map(
                    fn ($v) => $v === null ? 'NULL' : DB::getPdo()->quote((string) $v),
                    array_values($ligne),
                );
                $valeurs[] = '(' . implode(',', $cellules) . ')';
            }

            fwrite($f, $entete . "\n" . implode(",\n", $valeurs) . ";\n");
        }
    }

    fwrite($f, "\n");
    printf("  %-32s %6d ligne(s)\n", $table, $lignes->count());
}

fwrite($f, "SET FOREIGN_KEY_CHECKS = 1;\n");
fclose($f);

printf("\n%d ligne(s) au total\n", $total);
printf("Fichier : %s (%.1f Mo)\n", $sortie, filesize($sortie) / 1048576);
