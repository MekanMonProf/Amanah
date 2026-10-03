<?php

/*
 * Installateur AMANAH — à usage unique.
 *
 * L'hébergement n'a pas de SSH : cette page lance, depuis le navigateur, les
 * quelques commandes artisan qu'une installation demande. Elle n'en lance
 * aucune autre — la liste est écrite plus bas, en dur.
 *
 * Elle ne s'ouvre qu'avec le jeton AMANAH_INSTALLATION_JETON du .env, et se
 * supprime elle-même au bouton « Terminer ». Copiée dans public/ par
 * construire-archive.sh ; elle n'existe pas dans le dépôt sous public/.
 */

declare(strict_types=1);

@set_time_limit(300);
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');

$racine = dirname(__DIR__);

function e(string $texte): string
{
    return htmlspecialchars($texte, ENT_QUOTES, 'UTF-8');
}

// --- 1. Ce qui doit tenir avant même de démarrer Laravel --------------------

$controles = [];
$controles[] = ['PHP 8.2 ou plus récent', version_compare(PHP_VERSION, '8.2.0', '>='), 'version ' . PHP_VERSION];

foreach (['pdo_mysql', 'mbstring', 'openssl', 'tokenizer', 'xml', 'ctype', 'fileinfo', 'gd', 'zip', 'bcmath', 'intl'] as $ext) {
    $facultative = in_array($ext, ['gd', 'zip', 'bcmath', 'intl'], true);
    $controles[] = ["Extension $ext" . ($facultative ? ' (conseillée)' : ''), extension_loaded($ext) ?: ($facultative ? null : false), ''];
}

foreach (['storage', 'storage/framework/cache', 'storage/framework/sessions', 'storage/framework/views', 'storage/logs', 'storage/app/private', 'bootstrap/cache'] as $dossier) {
    $chemin = $racine . '/' . $dossier;
    $controles[] = ["Écriture dans $dossier", is_dir($chemin) && is_writable($chemin), ''];
}

$controles[] = ['Fichier .env présent', is_file($racine . '/.env'), 'à côté du dossier public, pas dedans'];
$controles[] = ['Dossier vendor présent', is_file($racine . '/vendor/autoload.php'), ''];

$bloquant = false;
foreach ($controles as [, $ok]) {
    if ($ok === false) {
        $bloquant = true;
    }
}

// --- 2. Démarrage de Laravel, puis le jeton ---------------------------------

$kernel = null;
$jeton = '';
$erreurDemarrage = null;

if (! $bloquant) {
    try {
        require $racine . '/vendor/autoload.php';
        $app = require $racine . '/bootstrap/app.php';
        $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
        $kernel->bootstrap();
        $jeton = (string) env('AMANAH_INSTALLATION_JETON', '');
    } catch (Throwable $ex) {
        $erreurDemarrage = $ex->getMessage();
    }
}

// Un jeton trop court se devine : on refuse de s'ouvrir plutôt que de s'en contenter.
$verrouille = strlen($jeton) < 32;

// --- 3. Les seules actions possibles ----------------------------------------

$actions = [
    'verifier'   => ['Vérifier la base',                 null,                                       []],
    'migrer'     => ['1. Créer les tables d\'AMANAH',     'migrate',                                  ['--force' => true]],
    'parametres' => ['2. Poser les paramètres et l\'administrateur', 'db:seed',                     ['--class' => 'ParametresInitiauxSeeder', '--force' => true]],
    'simuler'    => ['3. Simuler la reprise des comptes', 'amanah:reprendre-ancienne-application',   ['--simulation' => true]],
    'reprendre'  => ['4. Reprendre les comptes',          'amanah:reprendre-ancienne-application',   []],
    'coherence'  => ['5. Contrôler la cohérence',         'amanah:verifier-coherence',               []],
];

$resultat = null;
$jetonSaisi = (string) ($_POST['jeton'] ?? '');
$jetonValide = ! $verrouille && $kernel !== null && $jetonSaisi !== '' && hash_equals($jeton, $jetonSaisi);
$demande = (string) ($_POST['action'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $jetonSaisi !== '' && ! $jetonValide) {
    $resultat = ['Jeton', false, "Ce jeton n'est pas le bon. Il se trouve dans le .env, à la ligne AMANAH_INSTALLATION_JETON."];
} elseif ($jetonValide && $demande === 'terminer') {
    $supprime = @unlink(__FILE__);
    $resultat = ['Terminer', $supprime, $supprime
        ? "L'installateur s'est supprimé. Retirez aussi la ligne AMANAH_INSTALLATION_JETON du .env."
        : "Impossible de supprimer ce fichier : effacez public/installer.php à la main dans le gestionnaire de fichiers."];
    $jetonValide = false;
} elseif ($jetonValide && isset($actions[$demande])) {
    [$libelle, $commande, $options] = $actions[$demande];

    try {
        if ($commande === null) {
            $connexion = Illuminate\Support\Facades\DB::connection();
            // Laravel 12 liste toutes les bases visibles par défaut : on s'en tient à la nôtre.
            $tables = array_map(fn ($t) => $t['name'], Illuminate\Support\Facades\Schema::getTables($connexion->getDatabaseName()));
            $anciennes = array_values(array_intersect(['utilisateurs', 'releves', 'antennes'], $tables));
            $migrations = 'Aucune table AMANAH pour l\'instant : passez à l\'étape 1.';
            if (in_array('migrations', $tables, true)) {
                $kernel->call('migrate:status');
                $migrations = trim($kernel->output());
            }
            $sortie = 'Connexion à la base « ' . $connexion->getDatabaseName() . ' » : réussie.' . "\n"
                . count($tables) . " table(s) présente(s).\n"
                . 'Tables de l\'ancienne application trouvées : ' . (count($anciennes) === 3 ? 'oui (utilisateurs, releves, antennes)' : 'NON — ' . implode(', ', $anciennes)) . "\n"
                . 'Comptes AMANAH : ' . (in_array('users', $tables, true) ? Illuminate\Support\Facades\DB::table('users')->count() : 'table pas encore créée') . "\n\n"
                . $migrations;
            $resultat = [$libelle, true, $sortie];
        } else {
            $code = $kernel->call($commande, $options);
            $resultat = [$libelle, $code === 0, trim($kernel->output()) ?: '(aucune sortie)'];
        }
    } catch (Throwable $ex) {
        $resultat = [$libelle, false, get_class($ex) . ' : ' . $ex->getMessage()];
    }
}

?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Installation AMANAH</title>
<style>
  :root { --vert: #0e5e3f; --rouge: #a12a2a; --gris: #56645d; --bord: #dfe5e1; }
  * { box-sizing: border-box; }
  body { margin: 0; background: #f6f7f5; color: #1d2a24; font: 16px/1.5 system-ui, sans-serif; }
  main { max-width: 860px; margin: 0 auto; padding: 32px 16px 64px; }
  h1 { color: var(--vert); margin: 0 0 4px; }
  .sous { color: var(--gris); margin: 0 0 28px; }
  section { background: #fff; border: 1px solid var(--bord); border-radius: 12px; padding: 20px; margin-bottom: 20px; }
  h2 { font-size: 1.1rem; margin: 0 0 12px; }
  table { width: 100%; border-collapse: collapse; font-size: .95rem; }
  td { padding: 6px 4px; border-top: 1px solid var(--bord); }
  .ok { color: var(--vert); font-weight: 600; }
  .ko { color: var(--rouge); font-weight: 600; }
  .avis { color: #8a6d00; font-weight: 600; }
  label { display: block; font-weight: 600; margin-bottom: 6px; }
  input[type=password] { width: 100%; padding: 10px; border: 1px solid var(--bord); border-radius: 8px; font: inherit; }
  .boutons { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 16px; }
  button { padding: 10px 14px; border-radius: 8px; border: 2px solid var(--vert); background: #fff; color: var(--vert); font: inherit; font-weight: 600; cursor: pointer; }
  button.plein { background: var(--vert); color: #fff; }
  button.fin { border-color: var(--rouge); color: var(--rouge); }
  pre { background: #10231a; color: #e3efe8; padding: 16px; border-radius: 8px; overflow-x: auto; white-space: pre-wrap; word-break: break-word; font-size: .88rem; }
</style>
</head>
<body>
<main>
  <h1>Installation AMANAH</h1>
  <p class="sous">Page à usage unique. Supprimez-la avec le bouton « Terminer » une fois l'installation finie.</p>

  <section>
    <h2>Environnement du serveur</h2>
    <table>
      <?php foreach ($controles as [$nom, $ok, $note]): ?>
        <tr>
          <td><?= e($nom) ?></td>
          <td class="<?= $ok === true ? 'ok' : ($ok === null ? 'avis' : 'ko') ?>"><?= $ok === true ? 'OK' : ($ok === null ? 'absente' : 'MANQUE') ?></td>
          <td><?= e($note) ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
    <?php if ($bloquant): ?>
      <p class="ko">Corrigez les lignes « MANQUE » avant d'aller plus loin (voir le guide, étape « Version de PHP »).</p>
    <?php endif; ?>
    <?php if ($erreurDemarrage !== null): ?>
      <p class="ko">Laravel ne démarre pas : <?= e($erreurDemarrage) ?></p>
    <?php endif; ?>
  </section>

  <?php if ($resultat !== null): ?>
    <section>
      <h2><?= e($resultat[0]) ?> — <span class="<?= $resultat[1] ? 'ok' : 'ko' ?>"><?= $resultat[1] ? 'terminé' : 'échec' ?></span></h2>
      <pre><?= e($resultat[2]) ?></pre>
    </section>
  <?php endif; ?>

  <?php if (! $bloquant && $kernel !== null): ?>
    <section>
      <?php if ($verrouille): ?>
        <h2>Installateur verrouillé</h2>
        <p>La ligne <code>AMANAH_INSTALLATION_JETON</code> du .env est absente ou trop courte (32 caractères au moins).</p>
      <?php else: ?>
        <form method="post" autocomplete="off">
          <label for="jeton">Jeton d'installation</label>
          <input type="password" id="jeton" name="jeton" value="<?= $jetonValide ? e($jetonSaisi) : '' ?>" required>
          <div class="boutons">
            <?php foreach ($actions as $cle => [$libelle]): ?>
              <button type="submit" name="action" value="<?= e($cle) ?>" class="<?= $cle === 'verifier' ? 'plein' : '' ?>"><?= e($libelle) ?></button>
            <?php endforeach; ?>
            <button type="submit" name="action" value="terminer" class="fin"
                    onclick="return confirm('Supprimer l\'installateur ? Il faudra le redéposer pour s\'en resservir.')">Terminer et supprimer l'installateur</button>
          </div>
        </form>
      <?php endif; ?>
    </section>
  <?php endif; ?>
</main>
</body>
</html>
