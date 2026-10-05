<?php

/**
 * Deux corrections d'après-reprise.
 *
 *   1. Radiations — mon rejeu les a datées de la fin du mois où le grand livre les
 *      faisait apparaître, et leur a donné le numéro lu dans la colonne « N° RADIATION »
 *      de la feuille mensuelle. Or c'est la feuille RADIEES qui tient le registre : date
 *      exacte, numéro, montant, mode de paiement. 52 dates et 6 numéros étaient faux.
 *      On réapparie chaque radiation du registre avec celle de la base, par compte et
 *      par quantité, puis on recale numéro, date, montant et mode. Les écritures ne
 *      bougent pas — seule leur date suit — donc la chaîne des soldes reste intacte.
 *
 *   2. A0013 — la cellule « solde définitif » de sa ligne de juillet calculait W−U
 *      (payé moins reste) au lieu de U−W. L'erreur frappe deux fois : juillet 2025,
 *      où le solde devient +1 359,87 au lieu de −1 359,87, et juillet 2026, où il
 *      devient −13 306,80 au lieu de +13 306,80 alors qu'aucun versement n'a eu lieu.
 *      En août 2026, une action a été reprise (−1 dans « actions ajoutées ») pour
 *      rendre 25 000 CFA et ramener le solde à flot : un rattrapage de la formule,
 *      pas un mouvement réel. Mon rejeu a recopié ces trois anomalies en ajustements.
 *      Les retirer rétablit l'arithmétique.
 *
 * Usage : php storage/app/private/reprise/corriger_radiations_a0013.php
 */

require __DIR__ . '/../../vendor/autoload.php';

$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

require __DIR__ . '/extraire.php';

use App\Models\AuditLog;
use App\Models\CompteInvestissement;
use App\Models\Radiation;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

$administrateur = User::where('role', 'administrateur')->orderBy('id')->firstOrFail();
Auth::login($administrateur);

echo 'Base : ' . DB::connection()->getDatabaseName() . "\n\n";

$demander = function (string $question): bool {
    echo $question . ' [oui/non] : ';

    return in_array(strtolower(trim((string) fgets(STDIN))), ['oui', 'o', 'yes', 'y'], true);
};

$nombre = fn ($v) => $v === null || $v === '' ? 0.0 : (float) str_replace(' ', '', (string) $v);

// ------------------------------------------------- 1. Registre des radiations
$registre = [];

foreach (extraireFeuille(__DIR__ . '/c2026.xlsm', 'RADIEES', 11) as $c) {
    if (! isset($c['A']) || ! preg_match('/^A\d+/i', $c['A'])) {
        continue;
    }

    $registre[] = [
        'identifiant' => strtoupper($c['A']),
        'numero' => trim($c['D'] ?? ''),
        'date' => $c['E'] ?? '',
        'commercial' => (int) $nombre($c['F'] ?? ''),
        'waqf' => (int) $nombre($c['G'] ?? ''),
        'montant' => $nombre($c['L'] ?? ''),
        'mode' => trim($c['M'] ?? ''),
        'reference' => trim($c['N'] ?? ''),
    ];
}

echo '1. Registre RADIEES : ' . count($registre) . " ligne(s)\n";

$aCorriger = [];

foreach ($registre as $r) {
    foreach ([['commercial', $r['commercial']], ['waqf', $r['waqf']]] as [$categorie, $quantite]) {
        if ($quantite <= 0) {
            continue;
        }

        $compte = CompteInvestissement::whereHas('investisseur', fn ($q) => $q->where('identifiant_externe', $r['identifiant']))
            ->where('categorie', $categorie)->first();

        if (! $compte) {
            echo "   {$r['numero']} : compte {$r['identifiant']}-{$categorie} introuvable\n";
            continue;
        }

        // On apparie sur la quantité, puis à défaut sur la plus proche encore libre.
        $candidate = $compte->radiations()
            ->where('nombre_actions_radiees', $quantite)
            ->whereNotIn('id', array_column($aCorriger, 'id'))
            ->orderBy('date_radiation')
            ->first();

        if (! $candidate) {
            echo "   {$r['numero']} : aucune radiation de {$quantite} action(s) sur {$compte->numero_compte}\n";
            continue;
        }

        if ($candidate->numero_radiation !== $r['numero']
            || $candidate->date_radiation->toDateString() !== $r['date']
            || ($r['mode'] !== '' && $candidate->mode_paiement !== $r['mode'])) {
            $aCorriger[] = [
                'id' => $candidate->id,
                'avant' => $candidate->numero_radiation . ' du ' . $candidate->date_radiation->toDateString(),
                'apres' => $r['numero'] . ' du ' . $r['date'],
                'numero' => $r['numero'],
                'date' => $r['date'],
                'mode' => $r['mode'] ?: null,
                'reference' => $r['reference'] ?: null,
            ];
        }
    }
}

echo '   radiations à recaler : ' . count($aCorriger) . "\n";

foreach (array_slice($aCorriger, 0, 6) as $c) {
    echo "     {$c['avant']}  →  {$c['apres']}\n";
}

if ($aCorriger !== [] && $demander('   Appliquer ?')) {
    DB::transaction(function () use ($aCorriger) {
        // Un numéro du registre peut être détenu par une radiation qui n'est pas à
        // corriger — celles que la reprise a déduites ont pioché dans les mêmes numéros.
        // On libère donc tout le monde avant de réattribuer.
        foreach (Radiation::pluck('id') as $id) {
            Radiation::where('id', $id)->update(['numero_radiation' => 'TMP-' . $id]);
        }

        foreach ($aCorriger as $c) {
            $radiation = Radiation::find($c['id']);
            $radiation->update([
                'numero_radiation' => $c['numero'],
                'date_radiation' => $c['date'],
                'mode_paiement' => $c['mode'] ?? $radiation->mode_paiement,
                'reference_facture' => $c['reference'] ?? $radiation->reference_facture,
            ]);

            // Les deux écritures (capital crédité, capital versé) suivent la date.
            DB::table('ecritures_compte_financier')
                ->where('reference_type', 'radiations')
                ->where('reference_id', $radiation->id)
                ->update(['date_ecriture' => $c['date']]);
        }

        // Celles que le registre ne couvre pas — les sorties déduites de la position
        // finale, sans date ni montant d'origine — reprennent un numéro neutre.
        foreach (Radiation::where('numero_radiation', 'like', 'TMP-%')->with('compte.investisseur')->get() as $radiation) {
            $base = 'RAD-' . strtoupper($radiation->compte->investisseur->identifiant_externe)
                . '-' . $radiation->date_radiation->format('Ym')
                . ($radiation->compte->categorie === 'commercial' ? '-C' : '-W');

            $numero = $base;
            $suffixe = 1;

            while (Radiation::where('numero_radiation', $numero)->where('id', '!=', $radiation->id)->exists()) {
                $numero = $base . '-' . (++$suffixe);
            }

            $radiation->update(['numero_radiation' => $numero]);
        }
    });

    AuditLog::enregistrer(
        action: 'correction',
        entite: 'radiation',
        apres: [
            'operation' => 'numéros et dates recalés sur le registre RADIEES du classeur',
            'nombre' => count($aCorriger),
        ],
    );

    echo '   ' . count($aCorriger) . " radiation(s) recalée(s).\n";
}

// ------------------------------------------------------------- 2. A0013
$compte = CompteInvestissement::whereHas('investisseur', fn ($q) => $q->where('identifiant_externe', 'A0013'))
    ->where('categorie', 'commercial')->firstOrFail();

$ajustements = $compte->ecritures()
    ->where('type_ecriture', 'ajustement')
    ->where('observations', 'like', 'Correction reprise%')
    ->get();

echo "\n2. A0013 — ajustements recopiant la formule cassée : " . $ajustements->count() . "\n";

foreach ($ajustements as $a) {
    echo '     ' . $a->date_ecriture->toDateString() . '  ' . number_format($a->montant, 2, ',', ' ') . " CFA\n";
}

echo '   solde actuel : ' . number_format($compte->solde(), 2, ',', ' ') . ' CFA, '
    . $compte->nombreActions() . " actions\n";
echo '   après retrait : ' . number_format($compte->solde() - $ajustements->sum('montant'), 2, ',', ' ') . " CFA\n";

if ($ajustements->isNotEmpty() && $demander('   Les retirer et recalculer la chaîne des soldes ?')) {
    DB::transaction(function () use ($compte, $ajustements) {
        $compte->ecritures()->whereIn('id', $ajustements->pluck('id'))->delete();

        // Les soldes figés des écritures suivantes doivent être refaits : on rejoue
        // la chaîne dans l'ordre d'insertion, seul ordre qui fasse foi.
        $courant = 0.0;

        foreach ($compte->ecritures()->orderBy('id')->get() as $ecriture) {
            $courant = round($courant + (float) $ecriture->montant, 2);
            DB::table('ecritures_compte_financier')->where('id', $ecriture->id)->update(['solde_apres' => $courant]);
        }
    });

    AuditLog::enregistrer(
        action: 'correction',
        entite: 'compte',
        entiteId: $compte->id,
        apres: [
            'compte' => $compte->numero_compte,
            'motif' => 'retrait des ajustements recopiant une formule inversée du classeur (juillet 2025, juillet 2026, rattrapage d\'août 2026)',
            'ajustements_retires' => $ajustements->count(),
            'montant' => (float) $ajustements->sum('montant'),
        ],
    );

    $compte = $compte->fresh();
    echo '   Fait. Solde : ' . number_format($compte->solde(), 2, ',', ' ') . ' CFA, '
        . $compte->nombreActions() . " actions.\n";
}

echo "\nTerminé.\n";
