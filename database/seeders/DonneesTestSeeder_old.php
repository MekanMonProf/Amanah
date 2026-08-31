<?php

namespace Database\Seeders;

use App\Models\BaremeDividende;
use App\Models\CompteInvestissement;
use App\Models\Gestionnaire;
use App\Models\Investisseur;
use App\Models\PolitiqueInvestissement;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DonneesTestSeeder extends Seeder
{
    public function run(): void
    {
        // 1) Politiques d'investissement
        PolitiqueInvestissement::updateOrCreate(
            ['categorie' => 'commercial'],
            [
                'eligible_dividendes' => true, 'reinvestissement_par_defaut' => true,
                'versement_dividendes_possible' => true, 'versement_capital_radiation_possible' => true,
                'cession_autorisee' => true, 'radiation_autorisee' => true, 'prix_unitaire_action' => 25000,
            ]
        );
        PolitiqueInvestissement::updateOrCreate(
            ['categorie' => 'waqf'],
            [
                'eligible_dividendes' => true, 'reinvestissement_par_defaut' => true,
                'versement_dividendes_possible' => false, 'versement_capital_radiation_possible' => true,
                'cession_autorisee' => false, 'radiation_autorisee' => true, 'prix_unitaire_action' => 25000,
            ]
        );

        // 2) Deux gestionnaires de test
        $gestionnaires = [];
        foreach ([
            ['nom' => 'Diallo', 'prenom' => 'Fatoumata', 'email' => 'fatoumata.diallo@anddox.test'],
            ['nom' => 'Sarr', 'prenom' => 'Mamadou', 'email' => 'mamadou.sarr@anddox.test'],
        ] as $g) {
            $user = User::firstOrCreate(
                ['email' => $g['email']],
                ['nom' => $g['nom'], 'prenom' => $g['prenom'], 'password' => Hash::make('password'), 'role' => 'gestionnaire', 'actif' => true]
            );
            $gestionnaires[] = Gestionnaire::firstOrCreate(['user_id' => $user->id], ['actif' => true]);
        }

        // 3) Investisseurs de test
        $investisseursData = [
            ['nom' => 'Ndiaye', 'prenom' => 'Fatou', 'tel' => '771234501', 'pays' => 'Sénégal', 'ville' => 'Dakar'],
            ['nom' => 'Diop', 'prenom' => 'Moussa', 'tel' => '771234502', 'pays' => 'Sénégal', 'ville' => 'Thiès'],
            ['nom' => 'Ba', 'prenom' => 'Aïssatou', 'tel' => '771234503', 'pays' => 'Sénégal', 'ville' => 'Dakar'],
            ['nom' => 'Sarr', 'prenom' => 'Ibrahima', 'tel' => '771234504', 'pays' => 'Sénégal', 'ville' => 'Saint-Louis'],
            ['nom' => 'Fall', 'prenom' => 'Mariama', 'tel' => '771234505', 'pays' => 'Sénégal', 'ville' => 'Dakar'],
            ['nom' => 'Gueye', 'prenom' => 'Cheikh', 'tel' => '771234506', 'pays' => 'Sénégal', 'ville' => 'Kaolack'],
            ['nom' => 'Diallo', 'prenom' => 'Awa', 'tel' => '771234507', 'pays' => 'Sénégal', 'ville' => 'Dakar'],
            ['nom' => 'Kane', 'prenom' => 'Ousmane', 'tel' => '771234508', 'pays' => 'Sénégal', 'ville' => 'Ziguinchor'],
            ['nom' => 'Sow', 'prenom' => 'Khadija', 'tel' => '771234509', 'pays' => 'Sénégal', 'ville' => 'Dakar'],
            ['nom' => 'Ndoye', 'prenom' => 'Abdoulaye', 'tel' => '771234510', 'pays' => 'Sénégal', 'ville' => 'Rufisque'],
        ];

        $investisseurs = [];
        foreach ($investisseursData as $i => $data) {
            $numero = 'A' . str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT);

            $investisseurs[] = Investisseur::firstOrCreate(
                ['identifiant_externe' => $numero],
                [
                    'type_personne' => 'physique',
                    'nom' => $data['nom'],
                    'prenom' => $data['prenom'],
                    'telephone' => $data['tel'],
                    'email' => strtolower($data['prenom'] . '.' . $data['nom'] . '@example.test'),
                    'pays' => $data['pays'],
                    'ville' => $data['ville'],
                    'gestionnaire_id' => $gestionnaires[$i % count($gestionnaires)]->id,
                    'statut' => 'actif',
                ]
            );
        }

        $entreprises = [
            ['raison_sociale' => 'SARL Teranga Services', 'rccm' => 'SN.DKR.2021.B.1234', 'ninea' => '0012345678', 'representant' => 'Modou Wade', 'tel_rep' => '771234511'],
            ['raison_sociale' => 'GIE Baobab Commerce', 'rccm' => 'SN.DKR.2019.B.5678', 'ninea' => '0087654321', 'representant' => 'Astou Diagne', 'tel_rep' => '771234512'],
        ];
        foreach ($entreprises as $j => $e) {
            $numero = 'A' . str_pad((string) (count($investisseursData) + $j + 1), 4, '0', STR_PAD_LEFT);
            $investisseurs[] = Investisseur::firstOrCreate(
                ['identifiant_externe' => $numero],
                [
                    'type_personne' => 'morale',
                    'nom' => $e['raison_sociale'],
                    'raison_sociale' => $e['raison_sociale'],
                    'rccm' => $e['rccm'],
                    'ninea' => $e['ninea'],
                    'representant_legal_nom' => $e['representant'],
                    'representant_legal_telephone' => $e['tel_rep'],
                    'telephone' => $e['tel_rep'],
                    'pays' => 'Sénégal',
                    'ville' => 'Dakar',
                    'gestionnaire_id' => $gestionnaires[$j % count($gestionnaires)]->id,
                    'statut' => 'actif',
                ]
            );
        }

        // 4) Achats initiaux. Un compte sur trois est créé avec réinvestissement DÉSACTIVÉ.
        $modesPaiement = ['Wave', 'Orange Money', 'Espèces', 'Virement bancaire'];
        $prixAction = 25000;

        foreach ($investisseurs as $index => $investisseur) {
            $nbActionsCommercial = rand(4, 20);
            $compteCommercial = $investisseur->compteOuCree('commercial');

            if ($index % 3 === 1) {
                $compteCommercial->update(['reinvestissement_auto' => false]);
            }

            if ($compteCommercial->achats()->count() === 0) {
                $compteCommercial->achats()->create([
                    'numero_achat' => 'ACH-SEED-' . $investisseur->id . '-COM',
                    'date_achat' => now()->subMonths(rand(7, 10))->toDateString(),
                    'type_achat' => 'initial',
                    'nombre_actions' => $nbActionsCommercial,
                    'prix_unitaire' => $prixAction,
                    'montant' => $nbActionsCommercial * $prixAction,
                    'mode_paiement' => $modesPaiement[array_rand($modesPaiement)],
                    'observations' => 'Achat initial (donnée de test)',
                ]);
            }

            if ($index % 3 === 0) {
                $nbActionsWaqf = rand(2, 10);
                $compteWaqf = $investisseur->compteOuCree('waqf');

                if ($compteWaqf->achats()->count() === 0) {
                    $compteWaqf->achats()->create([
                        'numero_achat' => 'ACH-SEED-' . $investisseur->id . '-WAQF',
                        'date_achat' => now()->subMonths(rand(7, 10))->toDateString(),
                        'type_achat' => 'initial',
                        'nombre_actions' => $nbActionsWaqf,
                        'prix_unitaire' => $prixAction,
                        'montant' => $nbActionsWaqf * $prixAction,
                        'mode_paiement' => $modesPaiement[array_rand($modesPaiement)],
                        'observations' => 'Achat initial Waqf (donnée de test)',
                    ]);
                }
            }
        }

        // 5) Barèmes de dividendes sur 5 mois, du plus ancien au plus récent
        $baremesParMois = [
            5 => ['commercial' => 700, 'waqf' => 500],
            4 => ['commercial' => 750, 'waqf' => 500],
            3 => ['commercial' => 800, 'waqf' => 550],
            2 => ['commercial' => 650, 'waqf' => 550],
            1 => ['commercial' => 900, 'waqf' => 600],
        ];
        krsort($baremesParMois);

        foreach ($baremesParMois as $moisAvant => $baremes) {
            $periode = now()->subMonthsNoOverflow($moisAvant)->startOfMonth();

            foreach ($baremes as $categorie => $benefice) {
                BaremeDividende::firstOrCreate(
                    ['periode' => $periode->toDateString(), 'categorie' => $categorie],
                    ['benefice_par_action' => $benefice]
                );

                $comptes = CompteInvestissement::where('categorie', $categorie)->where('statut', 'actif')->get();

                foreach ($comptes as $compte) {
                    if ($compte->dividendes()->where('periode', $periode->toDateString())->exists()) {
                        continue;
                    }

                    $nbActions = $compte->nombreActions();
                    if ($nbActions <= 0) {
                        continue;
                    }

                    $montant = round($nbActions * $benefice, 2);

                    $dividende = $compte->dividendes()->create([
                        'periode' => $periode->toDateString(),
                        'nombre_actions' => $nbActions,
                        'benefice_par_action' => $benefice,
                        'montant_calcule' => $montant,
                        'statut' => 'credite',
                    ]);

                    $compte->ajouterEcriture(
                        type: 'dividende',
                        montant: $montant,
                        dateEcriture: $periode->toDateString(),
                        referenceType: 'dividendes',
                        referenceId: $dividende->id,
                        observations: 'Dividende ' . $periode->translatedFormat('F Y') . ' (donnée de test)',
                    );

                    $compte->tenterReinvestissementAutomatique();
                }
            }
        }

        // 6) Opérations manuelles supplémentaires : paiement, complément, ajustement
        $comptesCommerciauxAvecSolde = CompteInvestissement::where('categorie', 'commercial')
            ->where('reinvestissement_auto', false)
            ->get()
            ->filter(fn ($c) => $c->solde() > 0);

        if ($comptePourPaiement = $comptesCommerciauxAvecSolde->first()) {
            $montantPaiement = min(2000, $comptePourPaiement->solde());
            if ($montantPaiement > 0) {
                $comptePourPaiement->ajouterEcriture(
                    type: 'paiement',
                    montant: -$montantPaiement,
                    dateEcriture: now()->toDateString(),
                    observations: 'Versement à l\'investisseur — Wave (donnée de test)',
                );
            }
        }

        if ($comptePourComplement = CompteInvestissement::where('categorie', 'commercial')->first()) {
            $comptePourComplement->ajouterEcriture(
                type: 'versement_complementaire',
                montant: 15000,
                dateEcriture: now()->toDateString(),
                observations: 'Complément financier — Orange Money (donnée de test)',
            );
            $comptePourComplement->acheterActionsAvecSoldeDisponible('complement', 'Achat suite à complément financier (donnée de test)');
        }

        if ($comptePourAjustement = CompteInvestissement::where('categorie', 'waqf')->first()) {
            $comptePourAjustement->ajouterEcriture(
                type: 'ajustement',
                montant: 50,
                dateEcriture: now()->toDateString(),
                observations: 'Ajustement mineur d\'arrondi (donnée de test)',
            );
        }

        // 7) Une radiation de test (partiellement payée) sur le premier investisseur
        if (isset($investisseurs[0])) {
            $compteRadiation = $investisseurs[0]->compteOuCree('commercial');
            if ($compteRadiation->nombreActions() >= 2 && $compteRadiation->radiations()->count() === 0) {
                $radiation = $compteRadiation->radiations()->create([
                    'numero_radiation' => 'RAD-SEED-1',
                    'date_radiation' => now()->subDays(10)->toDateString(),
                    'nombre_actions_radiees' => 2,
                    'prix_unitaire_action' => $prixAction,
                    'montant_total' => 2 * $prixAction,
                    'observations' => 'Radiation de test (partiellement payée)',
                ]);
                $compteRadiation->ajouterEcriture(
                    type: 'radiation',
                    montant: 2 * $prixAction,
                    dateEcriture: now()->subDays(10)->toDateString(),
                    referenceType: 'radiations',
                    referenceId: $radiation->id,
                    observations: "Radiation {$radiation->numero_radiation} — capital disponible pour versement",
                );
                // Versement partiel volontaire, pour tester le badge "Partiel"
                $compteRadiation->ajouterEcriture(
                    type: 'paiement',
                    montant: -20000,
                    dateEcriture: now()->subDays(5)->toDateString(),
                    referenceType: 'radiations',
                    referenceId: $radiation->id,
                    observations: "Versement partiel du capital radié {$radiation->numero_radiation} (donnée de test)",
                );
            }
        }

        // 8) Deux comptes de connexion investisseur, pour tester le portail libre-service
        //    - A0001 (Fatou Ndiaye) : mot de passe temporaire, changement obligatoire à tester
        //    - A0002 (Moussa Diop)  : accès déjà actif, pour explorer directement le portail
        if (isset($investisseurs[0]) && $investisseurs[0]->email && ! $investisseurs[0]->user_id) {
            $userInv1 = User::firstOrCreate(
                ['email' => $investisseurs[0]->email],
                [
                    'nom' => $investisseurs[0]->nom, 'prenom' => $investisseurs[0]->prenom,
                    'telephone' => $investisseurs[0]->telephone, 'password' => Hash::make('password'),
                    'role' => 'investisseur', 'actif' => true, 'doit_changer_mot_de_passe' => true,
                ]
            );
            $investisseurs[0]->update(['user_id' => $userInv1->id]);
        }

        if (isset($investisseurs[1]) && $investisseurs[1]->email && ! $investisseurs[1]->user_id) {
            $userInv2 = User::firstOrCreate(
                ['email' => $investisseurs[1]->email],
                [
                    'nom' => $investisseurs[1]->nom, 'prenom' => $investisseurs[1]->prenom,
                    'telephone' => $investisseurs[1]->telephone, 'password' => Hash::make('password'),
                    'role' => 'investisseur', 'actif' => true, 'doit_changer_mot_de_passe' => false,
                ]
            );
            $investisseurs[1]->update(['user_id' => $userInv2->id]);
        }

        // 9) Quelques entrées d'exemple dans le journal d'audit, pour que la page ne soit pas
        //    vide juste après le seed (attribuées au premier gestionnaire, à défaut d'un
        //    utilisateur réellement connecté dans ce contexte de seed).
        $auteurAudit = $gestionnaires[0]->user_id ?? null;

        if ($auteurAudit) {
            \App\Models\AuditLog::insert([
                [
                    'user_id' => $auteurAudit, 'action' => 'creation', 'entite' => 'investisseur',
                    'entite_id' => $investisseurs[0]->id ?? null,
                    'donnees_apres' => json_encode(['identifiant_externe' => $investisseurs[0]->identifiant_externe ?? null, 'nom' => $investisseurs[0]->nom ?? null]),
                    'ip_address' => '127.0.0.1', 'created_at' => now()->subDays(6),
                ],
                [
                    'user_id' => $auteurAudit, 'action' => 'distribution_dividendes', 'entite' => 'bareme_dividende',
                    'entite_id' => null,
                    'donnees_apres' => json_encode(['periode_declenchee' => now()->subMonthNoOverflow()->format('Y-m'), 'commercial_total' => 12000, 'waqf_total' => 6000]),
                    'ip_address' => '127.0.0.1', 'created_at' => now()->subDays(3),
                ],
                [
                    'user_id' => $auteurAudit, 'action' => 'creation_acces_portail', 'entite' => 'investisseur',
                    'entite_id' => $investisseurs[0]->id ?? null,
                    'donnees_apres' => json_encode(['email' => $investisseurs[0]->email ?? null]),
                    'ip_address' => '127.0.0.1', 'created_at' => now()->subDays(1),
                ],
            ]);
        }

        $this->command?->info('Données de test créées, y compris 2 comptes de connexion investisseur (voir résumé affiché).');
        $this->command?->info('Portail investisseur — compte à changement obligatoire : ' . ($investisseurs[0]->email ?? '—') . ' / password');
        $this->command?->info('Portail investisseur — compte déjà actif : ' . ($investisseurs[1]->email ?? '—') . ' / password');
    }
}
