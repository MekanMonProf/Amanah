<?php

/**
 * Contrôle d'après-reprise : compare la position et le solde de chaque compte
 * avec le classeur Excel. À lancer depuis la racine du projet :
 *   php storage/app/private/reprise/verifier.php
 */

require __DIR__ . '/../../vendor/autoload.php';

$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$argv = ['', Illuminate\Support\Facades\DB::connection()->getDatabaseName()];

require __DIR__ . '/verifier_final.php';
