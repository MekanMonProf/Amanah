<?php

/**
 * Rejeu du grand livre mensuel (classeurs 2024-2025 et 2026) dans AMANAH.
 *
 * À lancer depuis la racine du projet :  php storage/app/private/reprise/lancer.php
 *
 * Une sauvegarde doit avoir été faite avant (php artisan backup:run).
 * Le script refuse de tourner sur une autre base que celle du .env.
 */

require __DIR__ . '/../../vendor/autoload.php';

$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$base = Illuminate\Support\Facades\DB::connection()->getDatabaseName();

echo "Base visée : {$base}\n";
echo "Tapez le nom de la base pour confirmer : ";

$reponse = trim((string) fgets(STDIN));

if ($reponse !== $base) {
    exit("Annulé.\n");
}

$argv = ['', $base];
require __DIR__ . '/rejouer_grand_livre.php';
