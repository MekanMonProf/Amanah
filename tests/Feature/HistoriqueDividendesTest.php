<?php

namespace Tests\Feature;

use App\Models\Investisseur;
use App\Models\User;
use App\Support\HistoriqueDividendes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * L'historique des dividendes vu par l'investisseur.
 *
 * Une règle porte cet écran : seuls les dividendes crédités y figurent. Un
 * dividende calculé puis annulé n'a jamais atteint le compte, et l'afficher
 * laisserait croire à un versement qui n'a pas eu lieu — sur un écran que
 * l'actionnaire lit pour savoir ce qu'il a touché.
 */
class HistoriqueDividendesTest extends TestCase
{
    private Investisseur $dossier;
    private int $compte;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'mysql',
            'database.connections.mysql.host' => '127.0.0.1',
            'database.connections.mysql.database' => 'amanah_repetition',
            'database.connections.mysql.username' => 'root',
            'database.connections.mysql.password' => '',
            'cache.default' => 'array',
        ]);
        DB::purge('mysql');
        DB::beginTransaction();

        $this->dossier = $this->dossierEnBase('Z9800');
        $this->compte = $this->compte($this->dossier, 'commercial');
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    public function test_un_dividende_annule_ne_figure_pas(): void
    {
        $this->dividende('2026-01-01', actions: 10, taux: 1000, montant: 10000);
        $this->dividende('2026-02-01', actions: 10, taux: 1200, montant: 12000, statut: 'annule');

        $historique = HistoriqueDividendes::pour($this->dossier);

        $this->assertSame(['2026-01'], $historique->pluck('periode')->all());
        $this->assertSame(10000.0, HistoriqueDividendes::cumul($historique)['total']);
    }

    public function test_les_mois_vont_du_plus_recent_au_plus_ancien(): void
    {
        foreach (['2025-11-01', '2026-03-01', '2025-12-01'] as $periode) {
            $this->dividende($periode, actions: 5, taux: 800, montant: 4000);
        }

        $this->assertSame(
            ['2026-03', '2025-12', '2025-11'],
            HistoriqueDividendes::pour($this->dossier)->pluck('periode')->all(),
        );
    }

    public function test_un_mois_rassemble_les_deux_comptes_sans_les_confondre(): void
    {
        $waqf = $this->compte($this->dossier, 'waqf');

        // Les deux catégories suivent des barèmes distincts : le même mois peut
        // porter deux taux, et les additionner les rendrait incompréhensibles.
        $this->dividende('2026-04-01', actions: 10, taux: 1000, montant: 10000);
        $this->dividende('2026-04-01', actions: 4, taux: 700, montant: 2800, compte: $waqf);

        $mois = HistoriqueDividendes::pour($this->dossier)->firstOrFail();

        $this->assertCount(2, $mois['lignes']);
        $this->assertSame(14, $mois['actions']);
        $this->assertSame(12800.0, $mois['montant']);
        $this->assertEqualsCanonicalizing([1000.0, 700.0], $mois['lignes']->pluck('taux')->all());
    }

    public function test_le_cumul_resume_ce_qui_a_ete_percu(): void
    {
        $this->dividende('2026-01-01', actions: 10, taux: 1000, montant: 10000);
        $this->dividende('2026-02-01', actions: 11, taux: 1100, montant: 12100);

        $cumul = HistoriqueDividendes::cumul(HistoriqueDividendes::pour($this->dossier));

        $this->assertSame(22100.0, $cumul['total']);
        $this->assertSame(2, $cumul['mois']);
        // Le dernier versement, pas le plus gros ni le premier.
        $this->assertSame(12100.0, $cumul['dernier']);
    }

    public function test_un_dossier_sans_dividende_ne_montre_rien(): void
    {
        $this->assertTrue(HistoriqueDividendes::pour($this->dossier)->isEmpty());
    }

    public function test_l_ecran_liste_les_mois_de_l_investisseur(): void
    {
        $this->dividende('2026-05-01', actions: 12, taux: 1500, montant: 18000);

        $connexion = User::create([
            'nom' => 'Essai',
            'prenom' => 'Dividendes',
            'telephone' => '+221779800980',
            'password' => Hash::make('quelquechose'),
            'role' => 'investisseur',
            'actif' => true,
            'doit_changer_mot_de_passe' => false,
        ]);
        $this->dossier->update(['user_id' => $connexion->id]);

        $this->actingAs($connexion)
            ->get(route('portail.dividendes.index'))
            ->assertOk()
            ->assertSee("Ce que j'ai perçu")
            ->assertSee(\App\Support\Montant::avecDevise(18000), false);
    }

    // ---- La hausse et la baisse du taux, d'un mois sur l'autre -----------

    /** Le taux de l'unique compte du dossier, pour un mois donné. */
    private function variation(string $periode): ?array
    {
        return HistoriqueDividendes::pour($this->dossier)
            ->firstWhere('periode', $periode)['lignes']
            ->first()['variation'];
    }

    public function test_la_variation_dit_le_sens_et_le_rapport(): void
    {
        $this->dividende('2026-01-01', actions: 10, taux: 1000, montant: 10000);
        $this->dividende('2026-02-01', actions: 10, taux: 1200, montant: 12000);
        $this->dividende('2026-03-01', actions: 10, taux: 900, montant: 9000);

        $this->assertSame('baisse', $this->variation('2026-03')['sens']);
        $this->assertSame(-25.0, $this->variation('2026-03')['pourcentage']);

        $this->assertSame('hausse', $this->variation('2026-02')['sens']);
        $this->assertSame(20.0, $this->variation('2026-02')['pourcentage']);
    }

    public function test_le_mois_le_plus_ancien_ne_se_compare_a_rien(): void
    {
        $this->dividende('2026-01-01', actions: 10, taux: 1000, montant: 10000);
        $this->dividende('2026-02-01', actions: 10, taux: 1000, montant: 10000);

        $this->assertNull($this->variation('2026-01'), "le premier mois n'a pas de précédent");
        $this->assertSame('stable', $this->variation('2026-02')['sens']);
    }

    public function test_un_mois_manquant_ne_fabrique_pas_une_envolee(): void
    {
        // Janvier, puis rien en février, puis mars : mars se compare à janvier.
        // Le comparer à un février absent reviendrait à le comparer à zéro.
        $this->dividende('2026-01-01', actions: 10, taux: 1000, montant: 10000);
        $this->dividende('2026-03-01', actions: 10, taux: 1100, montant: 11000);

        $variation = $this->variation('2026-03');

        $this->assertSame('hausse', $variation['sens']);
        $this->assertSame(1000.0, $variation['precedent']);
        $this->assertEqualsWithDelta(10.0, $variation['pourcentage'], 0.01);
    }

    public function test_le_taux_suit_son_propre_compte(): void
    {
        $waqf = $this->compte($this->dossier, 'waqf');

        // Le commercial monte, le waqf descend le même mois : chaque ligne
        // porte sa propre flèche, car chaque catégorie a son barème.
        $this->dividende('2026-01-01', actions: 10, taux: 1000, montant: 10000);
        $this->dividende('2026-01-01', actions: 5, taux: 800, montant: 4000, compte: $waqf);
        $this->dividende('2026-02-01', actions: 10, taux: 1200, montant: 12000);
        $this->dividende('2026-02-01', actions: 5, taux: 600, montant: 3000, compte: $waqf);

        $fevrier = HistoriqueDividendes::pour($this->dossier)->firstWhere('periode', '2026-02');
        $parTaux = $fevrier['lignes']->keyBy('taux');

        $this->assertSame('hausse', $parTaux[1200.0]['variation']['sens']);
        $this->assertSame('baisse', $parTaux[600.0]['variation']['sens']);
    }

    public function test_la_fiche_du_gestionnaire_porte_le_meme_historique(): void
    {
        $this->dividende('2026-05-01', actions: 12, taux: 1500, montant: 18000);

        $personnel = User::create([
            'nom' => 'Essai',
            'prenom' => 'Administration',
            'email' => 'dividendes.administration@local.test',
            'password' => Hash::make('quelquechose'),
            'role' => 'administrateur',
            'actif' => true,
            'doit_changer_mot_de_passe' => false,
        ]);

        // Le bloc arrive replié : c'est son intitulé et son compte qui se lisent.
        $this->actingAs($personnel)
            ->get(route('investisseurs.show', $this->dossier))
            ->assertOk()
            ->assertSee('Dividendes perçus')
            ->assertSee('(1)');
    }

    // ---- Les pièces du décor ----------------------------------------------

    private function dossierEnBase(string $identifiant): Investisseur
    {
        $gestionnaire = \App\Models\Gestionnaire::orderBy('id')->firstOrFail();

        return Investisseur::create([
            'identifiant_externe' => $identifiant,
            'type_personne' => 'physique',
            'nom' => 'Essai',
            'prenom' => 'Dividendes',
            'statut' => 'actif',
            'gestionnaire_id' => $gestionnaire->id,
        ]);
    }

    private function compte(Investisseur $investisseur, string $categorie): int
    {
        return DB::table('comptes_investissement')->insertGetId([
            'investisseur_id' => $investisseur->id,
            'categorie' => $categorie,
            'numero_compte' => $investisseur->identifiant_externe . '-' . strtoupper(substr($categorie, 0, 3)),
            'date_ouverture' => '2025-10-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function dividende(string $periode, int $actions, float $taux, float $montant, string $statut = 'credite', ?int $compte = null): void
    {
        DB::table('dividendes')->insert([
            'compte_id' => $compte ?? $this->compte,
            'periode' => $periode,
            'nombre_actions' => $actions,
            'benefice_par_action' => $taux,
            'montant_calcule' => $montant,
            'statut' => $statut,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
