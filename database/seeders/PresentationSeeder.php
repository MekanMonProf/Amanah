<?php

namespace Database\Seeders;

use App\Models\AchatAction;
use App\Models\BaremeDividende;
use App\Models\CompteInvestissement;
use App\Models\Don;
use App\Models\Gestionnaire;
use App\Models\Heritier;
use App\Models\HistoriqueAffectation;
use App\Models\Investisseur;
use App\Models\PolitiqueInvestissement;
use App\Models\Radiation;
use App\Models\User;
use App\Support\Observation;
use App\Support\Telephone;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Jeu de démonstration : onze mois d'activité, de novembre 2025 à aujourd'hui.
 *
 * Le parti pris est de ne rien fabriquer à la main que l'application sache faire
 * elle-même. Chaque événement est joué à sa date en remontant l'horloge —
 * Carbon::setTestNow() — puis passé aux méthodes du domaine : ajouterEcriture(),
 * acheterActionsAvecSoldeDisponible(), tenterReinvestissementAutomatique(). Les
 * soldes, les réinvestissements et les horodatages sont donc ceux qu'aurait
 * produits un usage réel, pas des valeurs posées à la main qui finiraient par ne
 * plus concorder.
 *
 * Le jeu couvre volontairement les cas qui font parler une démonstration : la
 * diaspora et ses numéros étrangers, les dossiers incomplets, les personnes
 * morales, les présents au waqf caritatif, une correction de barème, une
 * succession réglée et une en cours, des radiations à divers stades.
 */
class PresentationSeeder extends Seeder
{
    private const MOT_DE_PASSE = 'Amanah2026!';

    private const DEBUT = '2025-11-01';

    /** @var array<string, Gestionnaire> */
    private array $gestionnaires = [];

    private User $administrateur;

    /** @var array<string, Investisseur> */
    private array $investisseurs = [];

    /**
     * Les événements en attente, sous la forme [date, rang de déclaration, bloc].
     *
     * @var array<int, array{0: string, 1: int, 2: callable}>
     */
    private array $file = [];

    private bool $differer = false;

    public function run(): void
    {
        $this->command->info('Jeu de démonstration — novembre 2025 à aujourd’hui.');

        $this->tableRase();
        $this->politiques();
        $this->equipe();

        Auth::login($this->administrateur);

        // Les blocs qui suivent ne jouent rien : ils déclarent des événements datés.
        // C'est jouerLaChronologie() qui les exécute ensuite, du plus ancien au plus
        // récent — sans quoi l'ordre des appels ci-dessous ferait loi et l'on verrait,
        // par exemple, un dividende de novembre 2025 calculé sur des actions achetées
        // en février 2026.
        $this->differer = true;

        $this->portefeuille();
        $this->souscriptionsInitiales();
        $this->presentsAuWaqf();
        $this->complementsEtAchatsSurSolde();
        $this->baremesEtDividendes();
        $this->correctionDeBareme();
        $this->versementsDeDividendes();
        $this->radiations();
        $this->dons();
        $this->transfertsDeGestionnaire();
        $this->successions();

        $this->differer = false;
        $this->jouerLaChronologie();

        Auth::logout();
        Carbon::setTestNow();

        $this->bilan();
    }

    /**
     * Repart d'une base nette.
     *
     * Un jeu de démonstration doit pouvoir être rejoué : sans cela, une exécution
     * interrompue laisse des achats en double et des soldes qui ne veulent plus
     * rien dire. Seules les données métier sont effacées — les comptes de
     * connexion qui ne viennent pas de ce semeur, à commencer par celui de
     * l'exploitant, sont conservés.
     */
    private function tableRase(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');

        foreach ([
            'dons', 'heritiers', 'historique_affectations', 'ecritures_compte_financier',
            'dividendes', 'baremes_dividendes', 'radiations', 'achats_actions',
            'comptes_investissement', 'investisseurs', 'gestionnaires',
            // Le journal aussi : conservé, il garderait des entrées désignant des
            // fiches qui viennent d'être effacées, et mélangerait à la démonstration
            // les traces des essais précédents.
            'audit_logs',
        ] as $table) {
            DB::table($table)->truncate();
        }

        // Les accès portail et les gestionnaires. Ces derniers partent tous :
        // leurs fiches viennent d'être vidées, et un compte gestionnaire sans
        // fiche ne peut plus rien faire — autant ne pas le laisser derrière.
        // Les comptes de direction et d'administration, eux, ne sont pas touchés,
        // à l'exception de celui que ce semeur crée lui-même.
        User::whereIn('role', ['investisseur', 'gestionnaire'])
            ->orWhereIn('email', $this->adressesDeLEquipe())
            ->delete();

        DB::statement('SET FOREIGN_KEY_CHECKS = 1');

        $this->command->line('  Base métier remise à zéro.');
    }

    /** @return list<string> */
    private function adressesDeLEquipe(): array
    {
        return [
            'administrateur@anddox.sn',
            'mariama.diallo@anddox.sn',
            'mamadou.sarr@anddox.sn',
            'sokhna.ndoye@anddox.sn',
        ];
    }

    /** Consigne l'événement au journal, comme le ferait l'écran correspondant. */
    private function tracer(string $action, string $entite, ?int $id = null, ?array $apres = null): void
    {
        \App\Models\AuditLog::enregistrer(
            action: $action, entite: $entite, entiteId: $id, apres: $apres,
        );
    }

    /**
     * Un bloc d'événements à la date indiquée, comme s'ils s'y étaient produits.
     *
     * Tant que la déclaration est en cours, le bloc est mis en file ; il n'est joué
     * qu'à son rang chronologique. Hors de cette phase — la constitution de l'équipe,
     * qui doit exister avant tout le reste — il s'exécute immédiatement.
     */
    private function le(string $date, callable $action): void
    {
        if ($this->differer) {
            $this->file[] = [$date, count($this->file), $action];

            return;
        }

        $this->jouer($date, $action);
    }

    /**
     * Vide la file dans l'ordre des dates.
     *
     * À égalité de date, le rang de déclaration départage : deux événements du même
     * jour gardent l'ordre dans lequel les blocs les ont écrits, qui est celui qui a
     * du sens (on ouvre le compte avant d'y verser).
     */
    private function jouerLaChronologie(): void
    {
        $file = $this->file;
        $this->file = [];

        usort($file, fn (array $a, array $b) => [$a[0], $a[1]] <=> [$b[0], $b[1]]);

        foreach ($file as [$date, , $action]) {
            $this->jouer($date, $action);
        }
    }

    private function jouer(string $date, callable $action): void
    {
        Carbon::setTestNow(Carbon::parse($date)->setTime(10, 0));
        $action();
        Carbon::setTestNow();
    }

    private function politiques(): void
    {
        PolitiqueInvestissement::updateOrCreate(['categorie' => 'commercial'], [
            'eligible_dividendes' => true,
            'versement_dividendes_possible' => true, 'versement_capital_radiation_possible' => true,
            'cession_autorisee' => true, 'radiation_autorisee' => true, 'prix_unitaire_action' => 25000,
        ]);

        // Le waqf ne verse pas de dividendes et ne se cède pas : le capital est
        // immobilisé par nature. C'est la règle qui distingue les deux comptes.
        PolitiqueInvestissement::updateOrCreate(['categorie' => 'waqf'], [
            'eligible_dividendes' => true,
            'versement_dividendes_possible' => false, 'versement_capital_radiation_possible' => true,
            'cession_autorisee' => false, 'radiation_autorisee' => true, 'prix_unitaire_action' => 25000,
        ]);
    }

    private function equipe(): void
    {
        $this->le(self::DEBUT, function () {
            $this->administrateur = User::updateOrCreate(
                ['email' => 'administrateur@anddox.sn'],
                [
                    'nom' => 'Camara', 'prenom' => 'Ousseynou',
                    'telephone' => Telephone::normaliser('771000001'),
                    'whatsapp' => Telephone::normaliser('771000001'),
                    'password' => Hash::make(self::MOT_DE_PASSE),
                    'role' => 'administrateur', 'actif' => true, 'langue' => 'fr',
                    'doit_changer_mot_de_passe' => false,
                ],
            );

            foreach ([
                ['cle' => 'diallo', 'nom' => 'Diallo', 'prenom' => 'Mariama', 'tel' => '771000011'],
                ['cle' => 'sarr', 'nom' => 'Sarr', 'prenom' => 'Mamadou', 'tel' => '771000012'],
                ['cle' => 'ndoye', 'nom' => 'Ndoye', 'prenom' => 'Sokhna', 'tel' => '771000013'],
            ] as $g) {
                $user = User::updateOrCreate(
                    ['email' => strtolower($g['prenom'] . '.' . $g['nom']) . '@anddox.sn'],
                    [
                        'nom' => $g['nom'], 'prenom' => $g['prenom'],
                        'telephone' => Telephone::normaliser($g['tel']),
                        'whatsapp' => Telephone::normaliser($g['tel']),
                        'password' => Hash::make(self::MOT_DE_PASSE),
                        'role' => 'gestionnaire', 'actif' => true, 'langue' => 'fr',
                        'doit_changer_mot_de_passe' => false,
                    ],
                );

                $this->gestionnaires[$g['cle']] = Gestionnaire::updateOrCreate(
                    ['user_id' => $user->id], ['actif' => true],
                );
            }
        });
    }

    /**
     * Trente-deux dossiers, volontairement inégaux.
     *
     * Un portefeuille réel n'est pas homogène : des dossiers complets et d'autres
     * qui attendent une pièce, des résidents et de la diaspora, des sociétés, des
     * comptes sans gestionnaire. C'est cette variété qui rend la démonstration
     * parlante — un jeu uniforme ne montrerait aucun des garde-fous.
     */
    private function portefeuille(): void
    {
        foreach ($this->definitionsInvestisseurs() as $rang => $d) {
            $this->le($d['inscrit'], function () use ($d, $rang) {
                $complet = $d['complet'] ?? true;
                $morale = ($d['type'] ?? 'physique') === 'morale';

                $donnees = [
                    'identifiant_externe' => 'A' . str_pad((string) ($rang + 1), 4, '0', STR_PAD_LEFT),
                    'type_personne' => $morale ? 'morale' : 'physique',
                    'nom' => $d['nom'],
                    'prenom' => $d['prenom'] ?? null,
                    'telephone' => Telephone::normaliser($d['tel']),
                    'whatsapp' => Telephone::normaliser($d['whatsapp'] ?? $d['tel']),
                    'email' => $d['email'] ?? null,
                    'pays' => $d['pays'],
                    'ville' => $d['ville'] ?? null,
                    'gestionnaire_id' => isset($d['gestionnaire']) ? $this->gestionnaires[$d['gestionnaire']]->id : null,
                    'statut' => $d['statut'] ?? 'actif',
                    'langue' => $d['langue'] ?? \App\Support\Langue::DEFAUT,
                ];

                if ($complet) {
                    $donnees += $morale
                        ? [
                            'raison_sociale' => $d['raison_sociale'] ?? $d['nom'],
                            'rccm' => $d['rccm'] ?? 'SN-DKR-2024-B-' . (1000 + $rang),
                            'ninea' => $d['ninea'] ?? (string) (5000000 + $rang),
                            'adresse' => $d['adresse'] ?? 'Avenue Léopold Sédar Senghor',
                            'representant_legal_nom' => $d['representant'] ?? 'Ndiaye Aliou',
                            'representant_legal_telephone' => Telephone::normaliser('771000099'),
                            'representant_legal_whatsapp' => Telephone::normaliser('771000099'),
                            'convention_engagement_path' => 'conventions-engagement/demo-' . $rang . '.pdf',
                            'date_signature_convention' => $d['inscrit'],
                        ]
                        : [
                            'type_identification' => 'CNI',
                            'numero_identification' => $this->numeroCni($d['naissance'] ?? '1985-01-01', $rang),
                            'piece_identite_path' => 'pieces-identite/demo-' . $rang . '.jpg',
                            'date_naissance' => $d['naissance'] ?? '1985-01-01',
                            'lieu_naissance' => $d['ville'] ?? 'Dakar',
                            'nationalite' => $d['nationalite'] ?? 'Sénégalaise',
                            'adresse' => $d['adresse'] ?? 'Sicap Liberté 6, villa 1234',
                            'convention_engagement_path' => 'conventions-engagement/demo-' . $rang . '.pdf',
                            'date_signature_convention' => $d['inscrit'],
                        ];
                }

                $investisseur = Investisseur::updateOrCreate(
                    ['identifiant_externe' => $donnees['identifiant_externe']], $donnees,
                );

                // Accès au portail : une minorité seulement, comme dans la réalité.
                if (isset($d['acces'])) {
                    $identifiant = $d['acces'] === 'email'
                        ? ['email' => $d['email']]
                        : ['telephone' => Telephone::normaliser($d['tel'])];

                    $user = User::updateOrCreate(
                        $identifiant,
                        $identifiant + [
                            'nom' => $d['nom'], 'prenom' => $d['prenom'] ?? '',
                            'password' => Hash::make(self::MOT_DE_PASSE),
                            'role' => 'investisseur', 'actif' => true,
                            'langue' => $d['langue'] ?? \App\Support\Langue::DEFAUT,
                            'doit_changer_mot_de_passe' => false,
                            'whatsapp' => Telephone::normaliser($d['whatsapp'] ?? $d['tel']),
                        ],
                    );

                    $investisseur->update(['user_id' => $user->id]);

                    $this->tracer('creation_acces_portail', 'investisseur', $investisseur->id, [
                        'identifiant' => $user->email ?? $user->telephone,
                    ]);
                }

                $this->tracer('creation', 'investisseur', $investisseur->id, [
                    'identifiant_externe' => $investisseur->identifiant_externe,
                    'nom' => $investisseur->nom,
                    'prenom' => $investisseur->prenom,
                ]);

                $this->investisseurs[$donnees['identifiant_externe']] = $investisseur;
            });
        }
    }

    /** Numéro CEDEAO plausible : sexe, région, date de naissance, série, clé. */
    private function numeroCni(string $naissance, int $rang): string
    {
        return ($rang % 2 === 0 ? '1' : '2')
            . str_pad((string) (($rang % 14) + 1), 2, '0', STR_PAD_LEFT)
            . Carbon::parse($naissance)->format('Ymd')
            . str_pad((string) (10000 + $rang), 5, '0', STR_PAD_LEFT)
            . (string) ($rang % 10);
    }

    private function souscriptionsInitiales(): void
    {
        foreach ($this->definitionsInvestisseurs() as $rang => $d) {
            foreach ($d['achats'] ?? [] as $achat) {
                $this->le($achat['date'], function () use ($d, $rang, $achat) {
                    $investisseur = $this->investisseurs['A' . str_pad((string) ($rang + 1), 4, '0', STR_PAD_LEFT)];
                    $compte = $investisseur->compteOuCree($achat['categorie']);

                    if (isset($achat['reinvestissement'])) {
                        $compte->update(['reinvestissement_auto' => $achat['reinvestissement']]);
                    }

                    if ($achat['date'] < $compte->date_ouverture->toDateString()) {
                        $compte->update(['date_ouverture' => $achat['date']]);
                    }

                    $achatCree = $this->creerAchat($compte, $achat);

                    $this->tracer('creation', 'achat', $achatCree->id, [
                        'numero_achat' => $achatCree->numero_achat,
                        'nombre_actions' => $achatCree->nombre_actions,
                        'montant' => $achatCree->montant,
                    ]);
                });
            }
        }
    }

    private function creerAchat(CompteInvestissement $compte, array $achat, array $present = []): AchatAction
    {
        $prix = (float) $compte->politique()->prix_unitaire_action;

        return $compte->achats()->create([
            'numero_achat' => 'ACH-' . str_pad((string) (AchatAction::max('id') + 1), 5, '0', STR_PAD_LEFT),
            'date_achat' => $achat['date'],
            'type_achat' => $achat['type'] ?? 'initial',
            'nombre_actions' => $achat['actions'],
            'prix_unitaire' => $prix,
            'montant' => $achat['actions'] * $prix,
            'mode_paiement' => $achat['mode'] ?? 'Virement bancaire',
            'reference_facture' => $achat['reference'] ?? null,
            'saisi_par' => Auth::id(),
        ] + $present);
    }

    /**
     * Présents au waqf caritatif : les actions vont à l'institution, le donateur
     * n'est qu'une mention. C'est la fonctionnalité la moins évidente à expliquer
     * de vive voix, donc celle qu'il faut pouvoir montrer.
     */
    private function presentsAuWaqf(): void
    {
        $presents = [
            ['donateur' => 'A0003', 'date' => '2026-01-12', 'actions' => 4, 'type' => 'memoire',
             'pour' => 'Feu Amadou Bâ', 'lien' => 'Père'],
            ['donateur' => 'A0007', 'date' => '2026-03-20', 'actions' => 2, 'type' => 'memoire',
             'pour' => 'Feue Coumba Diop', 'lien' => 'Mère'],
            ['donateur' => 'A0011', 'date' => '2026-06-05', 'actions' => 6, 'type' => 'honneur',
             'pour' => 'Ibrahima Ndiaye', 'lien' => 'Ami'],
        ];

        foreach ($presents as $p) {
            $this->le($p['date'], function () use ($p) {
                $donateur = $this->investisseurs[$p['donateur']];
                $compte = Investisseur::waqfCaritatif()->compteOuCree('waqf');

                $this->creerAchat($compte, [
                    'date' => $p['date'], 'actions' => $p['actions'], 'type' => 'initial',
                    'mode' => 'Wave',
                ], [
                    'offert_par_investisseur_id' => $donateur->id,
                    'type_present' => $p['type'],
                    'present_pour' => $p['pour'],
                    'lien_avec_donateur' => $p['lien'],
                ]);
            });
        }
    }

    /**
     * Versement complémentaire puis achat sur le solde : le seul chemin par lequel
     * de l'argent entre sur le compte financier avant de devenir des actions.
     */
    private function complementsEtAchatsSurSolde(): void
    {
        $cas = [
            ['id' => 'A0002', 'date' => '2026-02-18', 'montant' => 300000, 'mode' => 'Orange Money'],
            ['id' => 'A0009', 'date' => '2026-04-09', 'montant' => 150000, 'mode' => 'Wave'],
            ['id' => 'A0015', 'date' => '2026-07-22', 'montant' => 525000, 'mode' => 'Virement bancaire'],
        ];

        foreach ($cas as $c) {
            $this->le($c['date'], function () use ($c) {
                $compte = $this->investisseurs[$c['id']]->compteOuCree('commercial');

                $compte->ajouterEcriture(
                    type: 'versement_complementaire',
                    montant: $c['montant'],
                    dateEcriture: $c['date'],
                    observationCle: Observation::COMPLEMENT,
                    observationParametres: ['mode' => $c['mode']],
                    userId: Auth::id(),
                );

                $compte->acheterActionsAvecSoldeDisponible(
                    'complement', Observation::ACHAT_COMPLEMENT, Auth::id(),
                );

                $this->tracer('complement_financier', 'compte_investissement', $compte->id, [
                    'montant' => $c['montant'], 'mode_paiement' => $c['mode'],
                ]);
            });
        }
    }

    /**
     * Barèmes mensuels sur toute la période, puis distribution — en reprenant
     * exactement la règle d'éligibilité de l'écran : il faut avoir détenu des
     * actions avant la fin du mois, délai de carence déduit.
     */
    private function baremesEtDividendes(): void
    {
        $delai = (int) (\App\Models\ParametreDividende::first()?->delai_eligibilite_jours ?? 2);
        $periode = Carbon::parse(self::DEBUT)->startOfMonth();
        $fin = Carbon::now()->startOfMonth()->subMonth();

        while ($periode <= $fin) {
            $mois = $periode->copy();

            $this->le($mois->copy()->endOfMonth()->toDateString(), function () use ($mois, $delai) {
                // Un rendement qui varie d'un mois à l'autre : une courbe plate
                // en démonstration donne l'impression d'un chiffre inventé. Le
                // même taux pour les deux catégories — le rendement vient du même
                // portefeuille, seule la destination du produit les distingue.
                $benefice = 900 + (($mois->month * 137) % 600);

                foreach (['commercial', 'waqf'] as $categorie) {
                    $bareme = BaremeDividende::updateOrCreate(
                        ['periode' => $mois->toDateString(), 'categorie' => $categorie],
                        ['benefice_par_action' => $benefice, 'fixe_par' => Auth::id()],
                    );

                    $distribues = $this->distribuer($bareme, $delai);

                    $this->tracer('distribution_dividendes', 'bareme_dividende', $bareme->id, [
                        'periode' => $bareme->periode->format('Y-m'),
                        'categorie' => $bareme->categorie,
                        'comptes' => $distribues,
                    ]);
                }
            });

            $periode->addMonth();
        }
    }

    private function distribuer(BaremeDividende $bareme, int $delai): int
    {
        $traites = 0;

        $limite = $bareme->periode->copy()->endOfMonth()->subDays($delai);

        $comptes = CompteInvestissement::where('categorie', $bareme->categorie)
            ->where('statut', 'actif')->get();

        foreach ($comptes as $compte) {
            if (! $compte->politique()?->eligible_dividendes) {
                continue;
            }

            if (! $compte->achats()->whereDate('date_achat', '<=', $limite)->exists()) {
                continue;
            }

            if ($compte->dividendes()->where('periode', $bareme->periode->toDateString())->exists()) {
                continue;
            }

            $actions = $compte->nombreActions();

            if ($actions <= 0) {
                continue;
            }

            $montant = round($actions * (float) $bareme->benefice_par_action, 2);

            $dividende = $compte->dividendes()->create([
                'periode' => $bareme->periode->toDateString(),
                'nombre_actions' => $actions,
                'benefice_par_action' => $bareme->benefice_par_action,
                'montant_calcule' => $montant,
                'statut' => 'credite',
            ]);

            $compte->ajouterEcriture(
                type: 'dividende',
                montant: $montant,
                dateEcriture: $bareme->periode->toDateString(),
                referenceType: 'dividendes',
                referenceId: $dividende->id,
                observationCle: Observation::DIVIDENDE,
                observationParametres: ['periode' => $bareme->periode->toDateString()],
                userId: Auth::id(),
            );

            $compte->tenterReinvestissementAutomatique(Auth::id());
            $traites++;
        }

        return $traites;
    }

    /**
     * Une correction de barème rétroactive : le cas qui montre que le système sait
     * revenir sur un calcul déjà distribué sans réécrire l'histoire — il ajoute
     * une écriture d'ajustement plutôt que de modifier les précédentes.
     */
    private function correctionDeBareme(): void
    {
        $this->le('2026-08-14', function () {
            $bareme = BaremeDividende::where('categorie', 'commercial')
                ->whereDate('periode', '2026-05-01')->first();

            if (! $bareme) {
                return;
            }

            $ancien = (float) $bareme->benefice_par_action;
            $nouveau = $ancien + 250;

            $dividendes = \App\Models\Dividende::with('compte')
                ->whereDate('periode', $bareme->periode->toDateString())
                ->whereHas('compte', fn ($q) => $q->where('categorie', $bareme->categorie))
                ->get();

            foreach ($dividendes as $dividende) {
                $delta = round($dividende->nombre_actions * ($nouveau - $ancien), 2);

                if ($delta == 0.0 || $dividende->compte->estSolde()) {
                    continue;
                }

                $dividende->compte->ajouterEcriture(
                    type: 'ajustement',
                    montant: $delta,
                    dateEcriture: '2026-08-14',
                    referenceType: 'dividendes',
                    referenceId: $dividende->id,
                    observationCle: Observation::CORRECTION_BAREME,
                    observationParametres: [
                        'periode' => $bareme->periode->toDateString(),
                        'categorie' => $bareme->categorie,
                        'ancien' => $ancien,
                        'nouveau' => $nouveau,
                        'motif' => 'Résultat définitif arrêté après audit du trimestre',
                    ],
                    userId: Auth::id(),
                );

                $dividende->update([
                    'benefice_par_action' => $nouveau,
                    'montant_calcule' => round($dividende->nombre_actions * $nouveau, 2),
                ]);

                $dividende->compte->tenterReinvestissementAutomatique(Auth::id());
            }

            $bareme->update(['benefice_par_action' => $nouveau]);

            $this->tracer('correction_bareme', 'bareme_dividende', $bareme->id, [
                'ancien_taux' => $ancien, 'nouveau_taux' => $nouveau,
                'motif' => 'Résultat définitif arrêté après audit du trimestre',
            ]);
        });
    }

    /** Les comptes sans réinvestissement automatique accumulent, puis on leur verse. */
    private function versementsDeDividendes(): void
    {
        $versements = [
            ['id' => 'A0004', 'date' => '2026-05-28', 'mode' => 'Orange Money', 'reference' => 'OM-558741'],
            ['id' => 'A0012', 'date' => '2026-07-15', 'mode' => 'Wave', 'reference' => null],
            ['id' => 'A0019', 'date' => '2026-09-03', 'mode' => 'Espèces', 'reference' => null],
        ];

        foreach ($versements as $v) {
            $this->le($v['date'], function () use ($v) {
                $compte = $this->investisseurs[$v['id']]->compteCommercial;

                if (! $compte || $compte->solde() <= 0) {
                    return;
                }

                // On ne verse jamais plus que le solde : c'est la règle de l'écran.
                $montant = min($compte->solde(), 100000);

                $compte->ajouterEcriture(
                    type: 'paiement',
                    montant: -$montant,
                    dateEcriture: $v['date'],
                    observationCle: Observation::selonReference(
                        Observation::VERSEMENT_INVESTISSEUR,
                        Observation::VERSEMENT_INVESTISSEUR_REF,
                        $v['reference'],
                    ),
                    observationParametres: ['mode' => $v['mode'], 'reference' => $v['reference']],
                    userId: Auth::id(),
                );

                $this->tracer('paiement', 'compte_investissement', $compte->id, [
                    'montant' => $montant, 'mode_paiement' => $v['mode'], 'reference' => $v['reference'],
                ]);
            });
        }
    }

    /**
     * Trois radiations à trois stades : payée, partiellement payée, en attente.
     * C'est ce qui permet de montrer le suivi du capital radié.
     */
    private function radiations(): void
    {
        $cas = [
            ['id' => 'A0006', 'date' => '2026-04-25', 'actions' => 3, 'paye' => 'tout'],
            ['id' => 'A0014', 'date' => '2026-06-30', 'actions' => 5, 'paye' => 'partie'],
            ['id' => 'A0021', 'date' => '2026-09-08', 'actions' => 2, 'paye' => 'rien'],
        ];

        foreach ($cas as $c) {
            $this->le($c['date'], function () use ($c) {
                $compte = $this->investisseurs[$c['id']]->compteCommercial;

                if (! $compte || $compte->nombreActions() < $c['actions']) {
                    return;
                }

                $prix = (float) $compte->politique()->prix_unitaire_action;
                $montant = $c['actions'] * $prix;

                $radiation = Radiation::create([
                    'compte_id' => $compte->id,
                    'numero_radiation' => 'RAD-' . str_pad((string) (Radiation::max('id') + 1), 4, '0', STR_PAD_LEFT),
                    'date_radiation' => $c['date'],
                    'nombre_actions_radiees' => $c['actions'],
                    'prix_unitaire_action' => $prix,
                    'montant_total' => $montant,
                    'mois_previsionnel_paiement' => Carbon::parse($c['date'])->addMonth()->startOfMonth()->toDateString(),
                    'observations' => 'Retrait partiel à la demande de l’investisseur',
                ]);

                // Le capital radié devient disponible sur le compte financier avant
                // d'être effectivement remis — les deux temps sont distincts.
                $compte->ajouterEcriture(
                    type: 'radiation',
                    montant: $montant,
                    dateEcriture: $c['date'],
                    referenceType: 'radiations',
                    referenceId: $radiation->id,
                    observationCle: Observation::RADIATION_CAPITAL,
                    observationParametres: ['numero' => $radiation->numero_radiation, 'actions' => $c['actions']],
                    userId: Auth::id(),
                );

                $aVerser = match ($c['paye']) {
                    'tout' => $montant,
                    'partie' => round($montant * 0.4, 2),
                    default => 0.0,
                };

                $this->tracer('creation', 'radiation', $radiation->id, [
                    'numero_radiation' => $radiation->numero_radiation,
                    'nombre_actions_radiees' => $c['actions'],
                    'montant_total' => $montant,
                ]);

                if ($aVerser > 0) {
                    $compte->ajouterEcriture(
                        type: 'paiement',
                        montant: -$aVerser,
                        dateEcriture: Carbon::parse($c['date'])->addDays(6)->toDateString(),
                        referenceType: 'radiations',
                        referenceId: $radiation->id,
                        observationCle: Observation::VERSEMENT_CAPITAL_RADIE,
                        observationParametres: ['mode' => 'Virement bancaire'],
                        userId: Auth::id(),
                    );

                    $this->tracer('paiement', 'compte_investissement', $compte->id, [
                        'montant' => $aVerser, 'mode_paiement' => 'Virement bancaire',
                        'radiation' => $radiation->numero_radiation,
                    ]);
                }
            });
        }
    }

    /** Un don d'actions ne touche pas le compte financier ; un don de solde, si. */
    private function dons(): void
    {
        $this->le('2026-05-10', function () {
            $source = $this->investisseurs['A0005']->compteCommercial;
            $destinataire = $this->investisseurs['A0008']->compteOuCree('commercial');

            if (! $source || $source->nombreActions() < 2) {
                return;
            }

            Don::create([
                'compte_source_id' => $source->id,
                'compte_destinataire_id' => $destinataire->id,
                'type_don' => 'actions', 'type_operation' => 'don',
                'nombre_actions' => 2,
                'prix_unitaire_action' => $source->politique()->prix_unitaire_action,
                'date_don' => '2026-05-10',
                'motif' => 'Don familial',
                'created_by' => Auth::id(),
            ]);

            $this->tracer('don', 'compte_investissement', $source->id, [
                'type_don' => 'actions', 'nombre_actions' => 2,
                'destinataire' => $destinataire->numero_compte, 'motif' => 'Don familial',
            ]);
        });

        $this->le('2026-08-02', function () {
            $source = $this->investisseurs['A0004']->compteCommercial;
            $destinataire = $this->investisseurs['A0013']->compteOuCree('commercial');

            if (! $source || $source->solde() < 10000) {
                return;
            }

            $montant = min($source->solde(), 50000);

            $don = Don::create([
                'compte_source_id' => $source->id,
                'compte_destinataire_id' => $destinataire->id,
                'type_don' => 'solde', 'type_operation' => 'don',
                'montant' => $montant,
                'date_don' => '2026-08-02',
                'motif' => 'Soutien à un proche',
                'created_by' => Auth::id(),
            ]);

            $source->ajouterEcriture(
                type: 'don_sortant', montant: -$montant, dateEcriture: '2026-08-02',
                referenceType: 'dons', referenceId: $don->id,
                observationCle: Observation::DON_SORTANT,
                observationParametres: [
                    'beneficiaire' => $destinataire->investisseur->nom,
                    'motif' => 'Soutien à un proche',
                ],
                userId: Auth::id(),
            );

            $destinataire->ajouterEcriture(
                type: 'don_entrant', montant: $montant, dateEcriture: '2026-08-02',
                referenceType: 'dons', referenceId: $don->id,
                observationCle: Observation::DON_ENTRANT,
                observationParametres: [
                    'donateur' => $source->investisseur->nom,
                    'motif' => 'Soutien à un proche',
                ],
                userId: Auth::id(),
            );

            $destinataire->tenterReinvestissementAutomatique(Auth::id());

            $this->tracer('don', 'compte_investissement', $source->id, [
                'type_don' => 'solde', 'montant' => $montant,
                'destinataire' => $destinataire->numero_compte, 'motif' => 'Soutien à un proche',
            ]);
        });
    }

    private function transfertsDeGestionnaire(): void
    {
        $transferts = [
            // Les dates suivent l'inscription de chacun : A0016 entre le 20 mars,
            // A0022 le 21 mai. Un transfert antérieur à l'inscription ne se jouait
            // que par l'ordre des blocs, et disparaissait dès qu'on rétablissait
            // la chronologie réelle.
            ['id' => 'A0016', 'date' => '2026-05-04', 'vers' => 'sarr', 'motif' => 'Rééquilibrage des portefeuilles'],
            ['id' => 'A0022', 'date' => '2026-07-06', 'vers' => 'ndoye', 'motif' => 'Rapprochement géographique'],
            ['id' => 'A0027', 'date' => '2026-08-11', 'vers' => 'diallo', 'motif' => 'Demande de l’investisseur'],
        ];

        foreach ($transferts as $t) {
            $this->le($t['date'], function () use ($t) {
                $investisseur = $this->investisseurs[$t['id']] ?? null;
                $nouveau = $this->gestionnaires[$t['vers']];

                if (! $investisseur || $investisseur->gestionnaire_id === $nouveau->id) {
                    return;
                }

                // Le modèle sait le faire : il consigne le transfert et réassigne
                // d'un seul geste, exactement comme depuis la fiche investisseur.
                $ancien = $investisseur->gestionnaire;

                $investisseur->transfererVers($nouveau, $t['motif'], Auth::id());

                $this->tracer('transfert_gestionnaire', 'investisseur', $investisseur->id, [
                    'ancien_gestionnaire' => $ancien?->user?->nom,
                    'nouveau_gestionnaire' => $nouveau->user?->nom,
                    'motif' => $t['motif'],
                ]);
            });
        }
    }

    /**
     * Deux successions : une réglée jusqu'au versement, une encore ouverte. La
     * seconde est celle qui apparaîtra dans « dossiers à régler ».
     */
    private function successions(): void
    {
        // — Succession réglée
        $this->le('2026-06-12', function () {
            $defunt = $this->investisseurs['A0025'];
            $defunt->update([
                'statut' => 'decede', 'date_deces' => '2026-06-08',
                'piece_acte_deces_path' => 'actes-deces/demo-25.pdf',
            ]);

            Heritier::create([
                'investisseur_id' => $defunt->id, 'nom' => 'Ndiaye', 'prenom' => 'Awa',
                'telephone' => Telephone::normaliser('771000031'),
                'whatsapp' => Telephone::normaliser('771000031'),
                'lien_parente' => 'Épouse', 'part_pourcentage' => 60,
            ]);

            Heritier::create([
                'investisseur_id' => $defunt->id, 'nom' => 'Ndiaye', 'prenom' => 'Modou',
                'telephone' => Telephone::normaliser('771000032'),
                'whatsapp' => Telephone::normaliser('771000032'),
                'lien_parente' => 'Fils', 'part_pourcentage' => 40,
            ]);

            $this->tracer('declaration_deces', 'investisseur', $defunt->id, [
                'date_deces' => '2026-06-08',
            ]);

            $this->tracer('designation_mandataire_succession', 'investisseur', $defunt->id, [
                'mandataire' => 'Ndiaye Awa', 'lien_parente' => 'Épouse',
            ]);
        });

        $this->le('2026-07-04', function () {
            $defunt = $this->investisseurs['A0025'];
            $compte = $defunt->compteCommercial;

            if (! $compte || $compte->nombreActions() <= 0) {
                return;
            }

            $nom = trim($defunt->nom . ' ' . $defunt->prenom);
            $actions = $compte->nombreActions();
            $prix = (float) $compte->politique()->prix_unitaire_action;

            $radiation = Radiation::create([
                'compte_id' => $compte->id,
                'numero_radiation' => Radiation::PREFIXE_SUCCESSION . $compte->id,
                'date_radiation' => '2026-07-04',
                'nombre_actions_radiees' => $actions,
                'prix_unitaire_action' => $prix,
                'montant_total' => $actions * $prix,
                'observations' => Observation::francais(Observation::LIQUIDATION_RADIATION, ['defunt' => $nom]),
                'observation_cle' => Observation::LIQUIDATION_RADIATION,
                'observation_parametres' => ['defunt' => $nom],
            ]);

            $compte->ajouterEcriture(
                type: 'radiation', montant: $actions * $prix, dateEcriture: '2026-07-04',
                referenceType: 'radiations', referenceId: $radiation->id,
                observationCle: Observation::LIQUIDATION_SUCCESSION,
                observationParametres: ['defunt' => $nom, 'actions' => $actions],
                userId: Auth::id(),
            );

            $compte->ajouterEcriture(
                type: 'paiement', montant: -$compte->solde(), dateEcriture: '2026-07-18',
                referenceType: 'succession_deces', referenceId: $defunt->id,
                observationCle: Observation::VERSEMENT_SUCCESSION,
                observationParametres: [
                    'defunt' => $nom, 'mandataire' => 'Ndiaye Awa', 'mode' => 'Virement bancaire',
                ],
                userId: Auth::id(),
            );

            $this->tracer('versement_succession', 'compte_investissement', $compte->id, [
                'defunt' => $nom, 'mandataire' => 'Ndiaye Awa', 'mode_paiement' => 'Virement bancaire',
            ]);

            $defunt->update(['succession_reglee' => true]);

            $this->tracer('reglement_succession', 'investisseur', $defunt->id, [
                'actions_liquidees' => $actions, 'mandataire' => 'Ndiaye Awa',
            ]);
        });

        // — Succession encore ouverte : c'est elle qui doit apparaître « à régler »
        $this->le('2026-09-05', function () {
            $defunt = $this->investisseurs['A0030'];
            $defunt->update([
                'statut' => 'decede', 'date_deces' => '2026-09-01',
                'piece_acte_deces_path' => 'actes-deces/demo-30.pdf',
                'succession_reglee' => false,
            ]);

            Heritier::create([
                'investisseur_id' => $defunt->id, 'nom' => 'Sow', 'prenom' => 'Fatou',
                'telephone' => Telephone::normaliser('771000041'),
                'whatsapp' => Telephone::normaliser('+33612345678'),
                'lien_parente' => 'Fille', 'part_pourcentage' => 100,
            ]);

            $this->tracer('declaration_deces', 'investisseur', $defunt->id, [
                'date_deces' => '2026-09-01',
            ]);

            $this->tracer('designation_mandataire_succession', 'investisseur', $defunt->id, [
                'mandataire' => 'Sow Fatou', 'lien_parente' => 'Fille',
            ]);
        });
    }

    private function bilan(): void
    {
        $lignes = [
            'Investisseurs' => Investisseur::count(),
            '  dont personnes morales' => Investisseur::where('type_personne', 'morale')->count(),
            '  dont dossiers incomplets' => Investisseur::incomplets()->count(),
            '  dont décédés' => Investisseur::where('statut', 'decede')->count(),
            'Gestionnaires' => Gestionnaire::count(),
            'Comptes' => CompteInvestissement::count(),
            'Achats d’actions' => AchatAction::count(),
            '  dont présents au waqf' => AchatAction::whereNotNull('type_present')->count(),
            'Écritures' => \App\Models\EcritureCompteFinancier::count(),
            'Barèmes' => BaremeDividende::count(),
            'Dividendes' => \App\Models\Dividende::count(),
            'Radiations' => Radiation::count(),
            'Dons' => Don::count(),
            'Héritiers' => Heritier::count(),
            'Transferts de gestionnaire' => HistoriqueAffectation::count(),
        ];

        foreach ($lignes as $libelle => $nombre) {
            $this->command->line(sprintf('  %-28s %d', $libelle, $nombre));
        }

        $this->command->info('Mot de passe commun : ' . self::MOT_DE_PASSE);
    }

    /**
     * Le portefeuille lui-même. Les dates d'inscription s'étalent sur toute la
     * période pour que le tableau de bord et les exports aient une histoire.
     *
     * @return list<array<string, mixed>>
     */
    private function definitionsInvestisseurs(): array
    {
        return [
            ['nom' => 'Ndiaye', 'prenom' => 'Fatou', 'tel' => '771100001', 'pays' => 'Sénégal', 'ville' => 'Dakar',
             'naissance' => '1982-04-12', 'gestionnaire' => 'diallo', 'inscrit' => '2025-11-03',
             'email' => 'fatou.ndiaye@example.sn', 'acces' => 'email',
             'achats' => [['date' => '2025-11-05', 'categorie' => 'commercial', 'actions' => 20, 'mode' => 'Virement bancaire']]],

            ['nom' => 'Diop', 'prenom' => 'Moussa', 'tel' => '771100002', 'pays' => 'Sénégal', 'ville' => 'Thiès',
             'naissance' => '1975-09-30', 'gestionnaire' => 'diallo', 'inscrit' => '2025-11-06',
             'achats' => [['date' => '2025-11-10', 'categorie' => 'commercial', 'actions' => 12, 'mode' => 'Wave']]],

            // Arabophone, et sans accès au portail : c'est sur elle que se montre le
            // message WhatsApp d'identifiants dans une autre langue que le français.
            ['nom' => 'Bâ', 'prenom' => 'Aïssatou', 'tel' => '771100003', 'pays' => 'Sénégal', 'ville' => 'Dakar',
             'naissance' => '1990-01-22', 'gestionnaire' => 'sarr', 'inscrit' => '2025-11-12', 'langue' => 'ar',
             'achats' => [
                 ['date' => '2025-11-15', 'categorie' => 'commercial', 'actions' => 8, 'mode' => 'Orange Money'],
                 ['date' => '2026-02-03', 'categorie' => 'waqf', 'actions' => 5, 'mode' => 'Wave'],
             ]],

            ['nom' => 'Sarr', 'prenom' => 'Ibrahima', 'tel' => '771100004', 'pays' => 'Sénégal', 'ville' => 'Saint-Louis',
             'naissance' => '1968-07-07', 'gestionnaire' => 'sarr', 'inscrit' => '2025-11-18',
             'achats' => [['date' => '2025-11-20', 'categorie' => 'commercial', 'actions' => 30, 'mode' => 'Chèque', 'reinvestissement' => false]]],

            ['nom' => 'Fall', 'prenom' => 'Mariama', 'tel' => '771100005', 'pays' => 'Sénégal', 'ville' => 'Dakar',
             'naissance' => '1988-11-11', 'gestionnaire' => 'ndoye', 'inscrit' => '2025-12-01',
             'achats' => [['date' => '2025-12-04', 'categorie' => 'commercial', 'actions' => 15, 'mode' => 'Virement bancaire']]],

            ['nom' => 'Gueye', 'prenom' => 'Cheikh', 'tel' => '771100006', 'pays' => 'Sénégal', 'ville' => 'Kaolack',
             'naissance' => '1979-03-19', 'gestionnaire' => 'ndoye', 'inscrit' => '2025-12-08',
             'achats' => [['date' => '2025-12-10', 'categorie' => 'commercial', 'actions' => 10, 'mode' => 'Espèces']]],

            ['nom' => 'Diallo', 'prenom' => 'Awa', 'tel' => '771100007', 'pays' => 'Sénégal', 'ville' => 'Dakar',
             'naissance' => '1993-06-25', 'gestionnaire' => 'diallo', 'inscrit' => '2025-12-15',
             'email' => 'awa.diallo@example.sn', 'acces' => 'email',
             'achats' => [['date' => '2025-12-18', 'categorie' => 'waqf', 'actions' => 9, 'mode' => 'Wave']]],

            ['nom' => 'Kane', 'prenom' => 'Ousmane', 'tel' => '771100008', 'pays' => 'Sénégal', 'ville' => 'Ziguinchor',
             'naissance' => '1984-02-14', 'gestionnaire' => 'sarr', 'inscrit' => '2026-01-07',
             'achats' => [['date' => '2026-01-09', 'categorie' => 'commercial', 'actions' => 6, 'mode' => 'Orange Money']]],

            ['nom' => 'Sow', 'prenom' => 'Khadija', 'tel' => '771100009', 'pays' => 'Sénégal', 'ville' => 'Dakar',
             'naissance' => '1991-08-08', 'gestionnaire' => 'ndoye', 'inscrit' => '2026-01-14',
             'acces' => 'telephone',
             'achats' => [['date' => '2026-01-16', 'categorie' => 'commercial', 'actions' => 11, 'mode' => 'Wave']]],

            ['nom' => 'Ndoye', 'prenom' => 'Abdoulaye', 'tel' => '771100010', 'pays' => 'Sénégal', 'ville' => 'Rufisque',
             'naissance' => '1972-12-02', 'gestionnaire' => 'diallo', 'inscrit' => '2026-01-20',
             'achats' => [['date' => '2026-01-22', 'categorie' => 'commercial', 'actions' => 25, 'mode' => 'Virement bancaire']]],

            ['nom' => 'Mbodj', 'prenom' => 'Bassirou', 'tel' => '771100011', 'pays' => 'Sénégal', 'ville' => 'Louga',
             'naissance' => '1986-05-05', 'gestionnaire' => 'sarr', 'inscrit' => '2026-02-02',
             'achats' => [['date' => '2026-02-05', 'categorie' => 'commercial', 'actions' => 18, 'mode' => 'Wave']]],

            ['nom' => 'Touré', 'prenom' => 'Aminata', 'tel' => '771100012', 'pays' => 'Sénégal', 'ville' => 'Dakar',
             'naissance' => '1995-10-17', 'gestionnaire' => 'ndoye', 'inscrit' => '2026-02-10',
             'achats' => [['date' => '2026-02-12', 'categorie' => 'commercial', 'actions' => 7, 'mode' => 'Orange Money', 'reinvestissement' => false]]],

            ['nom' => 'Cissé', 'prenom' => 'Modou', 'tel' => '771100013', 'pays' => 'Sénégal', 'ville' => 'Mbour',
             'naissance' => '1980-01-30', 'gestionnaire' => 'diallo', 'inscrit' => '2026-02-18',
             'achats' => [['date' => '2026-02-20', 'categorie' => 'commercial', 'actions' => 14, 'mode' => 'Chèque']]],

            ['nom' => 'Seck', 'prenom' => 'Ndèye', 'tel' => '771100014', 'pays' => 'Sénégal', 'ville' => 'Diourbel',
             'naissance' => '1987-04-04', 'gestionnaire' => 'sarr', 'inscrit' => '2026-03-03',
             'achats' => [['date' => '2026-03-05', 'categorie' => 'commercial', 'actions' => 22, 'mode' => 'Virement bancaire']]],

            ['nom' => 'Faye', 'prenom' => 'Alioune', 'tel' => '771100015', 'pays' => 'Sénégal', 'ville' => 'Dakar',
             'naissance' => '1977-09-09', 'gestionnaire' => 'ndoye', 'inscrit' => '2026-03-11',
             'achats' => [['date' => '2026-03-13', 'categorie' => 'commercial', 'actions' => 16, 'mode' => 'Wave']]],

            ['nom' => 'Diouf', 'prenom' => 'Rama', 'tel' => '771100016', 'pays' => 'Sénégal', 'ville' => 'Fatick',
             'naissance' => '1992-07-21', 'gestionnaire' => 'diallo', 'inscrit' => '2026-03-20',
             'achats' => [['date' => '2026-03-23', 'categorie' => 'waqf', 'actions' => 4, 'mode' => 'Orange Money']]],

            // — Diaspora : numéro étranger, WhatsApp distinct
            ['nom' => 'Ndour', 'prenom' => 'Pape', 'tel' => '+33612345601', 'whatsapp' => '771100017',
             'pays' => 'France', 'ville' => 'Paris', 'naissance' => '1983-03-03',
             'gestionnaire' => 'sarr', 'inscrit' => '2026-04-02', 'nationalite' => 'Sénégalaise',
             'adresse' => '14 rue de la République, 75011 Paris',
             'achats' => [['date' => '2026-04-05', 'categorie' => 'commercial', 'actions' => 40, 'mode' => 'Virement bancaire']]],

            ['nom' => 'Camara', 'prenom' => 'Bineta', 'tel' => '+393334567890', 'whatsapp' => '771100018',
             'pays' => 'Italie', 'ville' => 'Milan', 'naissance' => '1989-12-12',
             'gestionnaire' => 'ndoye', 'inscrit' => '2026-04-14', 'nationalite' => 'Sénégalaise',
             'adresse' => 'Via Torino 22, Milano',
             'achats' => [['date' => '2026-04-16', 'categorie' => 'waqf', 'actions' => 12, 'mode' => 'Virement bancaire']]],

            ['nom' => 'Wade', 'prenom' => 'Serigne', 'tel' => '+34612345602', 'pays' => 'Espagne', 'ville' => 'Madrid',
             'naissance' => '1974-06-06', 'gestionnaire' => 'diallo', 'inscrit' => '2026-04-25',
             'complet' => false,
             'achats' => [['date' => '2026-04-28', 'categorie' => 'commercial', 'actions' => 9, 'mode' => 'Virement bancaire', 'reinvestissement' => false]]],

            ['nom' => 'Barry', 'prenom' => 'Oumou', 'tel' => '+12025550143', 'whatsapp' => '771100020',
             'pays' => 'États-Unis', 'ville' => 'New York', 'naissance' => '1996-02-28',
             'gestionnaire' => 'sarr', 'inscrit' => '2026-05-06', 'complet' => false,
             'achats' => [['date' => '2026-05-08', 'categorie' => 'commercial', 'actions' => 13, 'mode' => 'Virement bancaire']]],

            ['nom' => 'Thiam', 'prenom' => 'Lamine', 'tel' => '771100021', 'pays' => 'Sénégal', 'ville' => 'Dakar',
             'naissance' => '1981-11-19', 'gestionnaire' => 'ndoye', 'inscrit' => '2026-05-12',
             'achats' => [['date' => '2026-05-14', 'categorie' => 'commercial', 'actions' => 10, 'mode' => 'Wave']]],

            ['nom' => 'Ly', 'prenom' => 'Adama', 'tel' => '771100022', 'pays' => 'Sénégal', 'ville' => 'Tambacounda',
             'naissance' => '1985-08-15', 'gestionnaire' => 'diallo', 'inscrit' => '2026-05-21',
             'complet' => false,
             'achats' => [['date' => '2026-05-25', 'categorie' => 'commercial', 'actions' => 5, 'mode' => 'Espèces']]],

            // Arabophone avec un accès au portail : en se connectant sous son compte,
            // c'est tout le portail investisseur qui s'affiche en arabe.
            ['nom' => 'Sy', 'prenom' => 'Mame Diarra', 'tel' => '771100023', 'pays' => 'Sénégal', 'ville' => 'Dakar',
             'naissance' => '1994-04-09', 'gestionnaire' => 'sarr', 'inscrit' => '2026-06-02',
             'acces' => 'telephone', 'langue' => 'ar',
             'achats' => [['date' => '2026-06-04', 'categorie' => 'waqf', 'actions' => 7, 'mode' => 'Orange Money']]],

            ['nom' => 'Badji', 'prenom' => 'Ansoumana', 'tel' => '771100024', 'pays' => 'Sénégal', 'ville' => 'Ziguinchor',
             'naissance' => '1970-10-10', 'gestionnaire' => 'ndoye', 'inscrit' => '2026-06-10',
             'complet' => false,
             'achats' => [['date' => '2026-06-12', 'categorie' => 'commercial', 'actions' => 6, 'mode' => 'Wave']]],

            ['nom' => 'Ndiaye', 'prenom' => 'Souleymane', 'tel' => '771100025', 'pays' => 'Sénégal', 'ville' => 'Dakar',
             'naissance' => '1960-05-01', 'gestionnaire' => 'diallo', 'inscrit' => '2025-11-25',
             'achats' => [['date' => '2025-11-28', 'categorie' => 'commercial', 'actions' => 24, 'mode' => 'Virement bancaire']]],

            ['nom' => 'Mbaye', 'prenom' => 'Coumba', 'tel' => '771100026', 'pays' => 'Sénégal', 'ville' => 'Kaolack',
             'naissance' => '1997-01-16', 'gestionnaire' => 'sarr', 'inscrit' => '2026-06-24',
             'complet' => false, 'statut' => 'inactif'],

            ['nom' => 'Dièye', 'prenom' => 'Malick', 'tel' => '771100027', 'pays' => 'Sénégal', 'ville' => 'Thiès',
             'naissance' => '1978-07-28', 'gestionnaire' => 'ndoye', 'inscrit' => '2026-07-01',
             'achats' => [['date' => '2026-07-03', 'categorie' => 'commercial', 'actions' => 19, 'mode' => 'Chèque']]],

            ['nom' => 'Samb', 'prenom' => 'Astou', 'tel' => '771100028', 'pays' => 'Sénégal', 'ville' => 'Dakar',
             'naissance' => '1990-09-05', 'gestionnaire' => 'sarr', 'inscrit' => '2026-07-15', 'complet' => false,
             'achats' => [['date' => '2026-07-18', 'categorie' => 'commercial', 'actions' => 8, 'mode' => 'Wave']]],

            ['nom' => 'Diagne', 'prenom' => 'Babacar', 'tel' => '771100029', 'pays' => 'Sénégal', 'ville' => 'Dakar',
             'naissance' => '1966-03-23', 'gestionnaire' => 'diallo', 'inscrit' => '2026-08-04',
             'complet' => false, 'statut' => 'inactif'],

            ['nom' => 'Sow', 'prenom' => 'Ibrahima', 'tel' => '771100030', 'pays' => 'Sénégal', 'ville' => 'Podor',
             'naissance' => '1955-02-11', 'gestionnaire' => 'sarr', 'inscrit' => '2025-12-20',
             'achats' => [['date' => '2025-12-22', 'categorie' => 'commercial', 'actions' => 17, 'mode' => 'Virement bancaire']]],

            // — Personnes morales
            ['nom' => 'GIE Baobab Commerce', 'type' => 'morale', 'tel' => '338001001',
             'raison_sociale' => 'GIE Baobab Commerce', 'pays' => 'Sénégal', 'ville' => 'Dakar',
             'gestionnaire' => 'ndoye', 'inscrit' => '2026-02-25',
             'representant' => 'Diouf Serigne',
             'achats' => [['date' => '2026-02-27', 'categorie' => 'commercial', 'actions' => 35, 'mode' => 'Virement bancaire']]],

            ['nom' => 'SARL Teranga Services', 'type' => 'morale', 'tel' => '338001002',
             'raison_sociale' => 'SARL Teranga Services', 'pays' => 'Sénégal', 'ville' => 'Dakar',
             'gestionnaire' => 'diallo', 'inscrit' => '2026-05-30', 'complet' => false,
             'achats' => [['date' => '2026-06-02', 'categorie' => 'commercial', 'actions' => 28, 'mode' => 'Chèque']]],

            ['nom' => 'Coopérative Nouvelle Aube', 'type' => 'morale', 'tel' => '338001003',
             'raison_sociale' => 'Coopérative Nouvelle Aube', 'pays' => 'Sénégal', 'ville' => 'Saint-Louis',
             'gestionnaire' => 'sarr', 'inscrit' => '2026-07-28',
             'representant' => 'Fall Mamadou',
             'achats' => [['date' => '2026-07-30', 'categorie' => 'waqf', 'actions' => 20, 'mode' => 'Virement bancaire']]],

            ['nom' => 'Fondation Jappoo', 'type' => 'morale', 'tel' => '338001004',
             'raison_sociale' => 'Fondation Jappoo', 'pays' => 'Sénégal', 'ville' => 'Dakar',
             'gestionnaire' => 'ndoye', 'inscrit' => '2026-09-02',
             'representant' => 'Sow Awa',
             'achats' => [['date' => '2026-09-04', 'categorie' => 'waqf', 'actions' => 15, 'mode' => 'Virement bancaire']]],
        ];
    }
}
