<?php

/**
 * Bascule l'application entre la base réelle et celle de démonstration.
 *
 * Le `.env` est le seul endroit qui dise sur quelle base l'application
 * travaille, et il n'en porte qu'une : on la change donc, on ne la double pas.
 * Le script garde le nom de la base réelle dans une ligne commentée, pour que
 * le retour ne dépende pas de la mémoire de qui a basculé.
 *
 * Sans argument, il dit seulement où l'on en est — c'est le geste à faire avant
 * de lancer un enregistrement, et avant toute opération sur de vraies données.
 *
 * Usage : php database/demo/basculer.php            (dit où l'on en est)
 *         php database/demo/basculer.php demo       (passe à la démonstration)
 *         php database/demo/basculer.php reelle     (revient aux vrais dossiers)
 */

$racine = dirname(__DIR__, 2);
$chemin = $racine . '/.env';

const MARQUE = '# AMANAH_BASE_REELLE=';

if (! file_exists($chemin)) {
    echo "ABANDON : aucun fichier .env à {$chemin}\n";
    exit(1);
}

$lignes = file($chemin, FILE_IGNORE_NEW_LINES);

$lire = function (array $lignes, string $prefixe): ?array {
    foreach ($lignes as $i => $ligne) {
        if (str_starts_with(ltrim($ligne), $prefixe)) {
            return [$i, trim(substr(ltrim($ligne), strlen($prefixe)), " \t\"'")];
        }
    }

    return null;
};

$courante = $lire($lignes, 'DB_DATABASE=');

if ($courante === null) {
    echo "ABANDON : le .env ne porte aucune ligne DB_DATABASE.\n";
    exit(1);
}

[$placeCourante, $nomCourant] = $courante;
$memoire = $lire($lignes, MARQUE);
$nomReel = $memoire[1] ?? null;

$estDemo = str_contains($nomCourant, 'demo');

$dire = function () use ($nomCourant, $estDemo, $nomReel): void {
    echo "\n  Base active : {$nomCourant}";
    echo $estDemo ? "   ← DÉMONSTRATION\n" : "   ← DOSSIERS RÉELS\n";

    if ($estDemo && $nomReel) {
        echo "  Retour prévu vers : {$nomReel}\n";
    }

    echo "\n";
};

$voulu = $argv[1] ?? null;

if ($voulu === null) {
    $dire();
    echo "Pour changer : php database/demo/basculer.php demo | reelle\n";
    exit;
}

if (! in_array($voulu, ['demo', 'reelle'], true)) {
    echo "ABANDON : l'argument doit être « demo » ou « reelle ».\n";
    exit(1);
}

if ($voulu === 'demo') {
    if ($estDemo) {
        echo "Déjà sur la démonstration.\n";
        $dire();
        exit;
    }

    $cible = 'amanah_demo';

    // On retient d'où l'on vient avant de partir : la ligne commentée survit
    // au basculement et rend le retour sûr, même des semaines plus tard.
    $lignes[$placeCourante] = "DB_DATABASE={$cible}";

    if ($memoire !== null) {
        $lignes[$memoire[0]] = MARQUE . $nomCourant;
    } else {
        array_splice($lignes, $placeCourante, 0, [MARQUE . $nomCourant]);
    }
} else {
    if (! $estDemo) {
        echo "Déjà sur les dossiers réels.\n";
        $dire();
        exit;
    }

    if ($nomReel === null) {
        echo "ABANDON : le .env ne garde pas trace de la base réelle.\n";
        echo "Remettez la ligne DB_DATABASE à la main.\n";
        exit(1);
    }

    $lignes[$placeCourante] = "DB_DATABASE={$nomReel}";
}

file_put_contents($chemin, implode("\n", $lignes) . "\n");

$apres = $voulu === 'demo' ? 'amanah_demo' : $nomReel;

echo "\n  Base active : {$apres}";
echo $voulu === 'demo' ? "   ← DÉMONSTRATION\n\n" : "   ← DOSSIERS RÉELS\n\n";

// Laravel garde la configuration en cache sur une installation en production :
// sans ce vidage, la bascule n'aurait aucun effet et personne ne verrait pourquoi.
echo "Pensez à vider le cache de configuration si vous l'utilisez :\n";
echo "  php artisan config:clear\n";
