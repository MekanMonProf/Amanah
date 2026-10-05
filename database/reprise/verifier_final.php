<?php
require __DIR__ . '/extraire.php';

$base = new PDO('mysql:host=127.0.0.1;dbname=' . ($argv[1] ?? 'amanah_essai_import'), 'root', '');
$n = fn ($v) => $v === null || $v === '' ? 0.0 : (float) str_replace(' ', '', (string) $v);

$excel = [];
foreach (extraireFeuille(__DIR__ . '/c2026.xlsm', 'ACTIONNAIRES', 11) as $c) {
    if (isset($c['B']) && preg_match('/^A\d+/i', $c['B'])) {
        $excel[strtoupper($c['B'])] = $c;
    }
}

// Position réelle = achats - radiations, comme le fait CompteInvestissement::nombreActions()
$positions = [];
foreach ($base->query("
    SELECT i.identifiant_externe id, c.categorie,
           (SELECT COALESCE(SUM(nombre_actions),0) FROM achats_actions WHERE compte_id = c.id)
         - (SELECT COALESCE(SUM(nombre_actions_radiees),0) FROM radiations WHERE compte_id = c.id) AS actions,
           (SELECT solde_apres FROM ecritures_compte_financier WHERE compte_id = c.id ORDER BY id DESC LIMIT 1) AS solde
    FROM comptes_investissement c JOIN investisseurs i ON i.id = c.investisseur_id") as $l) {
    $positions[strtoupper($l['id']) . '|' . $l['categorie']] = ['actions' => (int) $l['actions'], 'solde' => (float) $l['solde']];
}

$ecartsActions = $ecartsSolde = [];
$totalActionsCom = $totalActionsWaqf = 0;
$totalSolde = 0.0;

foreach ($excel as $id => $a) {
    foreach ([['commercial', 'AB', 'AH'], ['waqf', 'AC', 'AI']] as [$cat, $colActions, $colSolde]) {
        $attenduActions = (int) $n($a[$colActions] ?? '');
        $attenduSolde = round($n($a[$colSolde] ?? ''), 2);
        $reel = $positions[$id . '|' . $cat] ?? ['actions' => 0, 'solde' => 0.0];

        if ($attenduActions !== $reel['actions']) {
            $ecartsActions[] = sprintf('  %s %-11s actions : classeur=%-6d base=%-6d (%+d)', $id, $cat, $attenduActions, $reel['actions'], $reel['actions'] - $attenduActions);
        }
        if (abs($attenduSolde - $reel['solde']) > 0.02) {
            $ecartsSolde[] = sprintf('  %s %-11s solde : classeur=%s base=%s (%s)', $id, $cat,
                number_format($attenduSolde, 2, ',', ' '), number_format($reel['solde'], 2, ',', ' '), number_format($reel['solde'] - $attenduSolde, 2, ',', ' '));
        }
    }
}

foreach ($positions as $cle => $p) {
    [, $cat] = explode('|', $cle);
    if ($cat === 'commercial') { $totalActionsCom += $p['actions']; } else { $totalActionsWaqf += $p['actions']; }
    $totalSolde += $p['solde'];
}

echo 'Écarts sur les actions : ' . count($ecartsActions) . "\n";
foreach (array_slice($ecartsActions, 0, 12) as $e) { echo $e . "\n"; }
echo "\nÉcarts sur les soldes (> 2 centimes) : " . count($ecartsSolde) . "\n";
foreach (array_slice($ecartsSolde, 0, 12) as $e) { echo $e . "\n"; }

echo "\nTotaux base : " . number_format($totalActionsCom, 0, ',', ' ') . ' commercial, '
    . number_format($totalActionsWaqf, 0, ',', ' ') . ' waqf, solde ' . number_format($totalSolde, 2, ',', ' ') . " CFA\n";
echo "Totaux classeur : 18 585 commercial, 3 653 waqf, solde 5 329 636,04 CFA\n";
