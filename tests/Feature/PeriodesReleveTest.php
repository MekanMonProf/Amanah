<?php

namespace Tests\Feature;

use App\Models\Investisseur;
use App\Support\PeriodesReleve;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Les relevés mensuels : quels mois sont proposés, et sous quel nom.
 *
 * Le nom compte autant que la liste. L'écran l'affiche avant que le PDF existe ;
 * s'il ne concorde pas avec celui que le téléchargement livre, la liste ment —
 * c'est exactement le défaut que ce fichier verrouille.
 */
class PeriodesReleveTest extends TestCase
{
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
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    // ---- Le nom du fichier -------------------------------------------------

    public static function periodes(): array
    {
        return [
            'un mois entier' => ['2026-07-01', '2026-07-31', 'Releve_Z9300_Juillet_2026.pdf'],
            'février non bissextile' => ['2026-02-01', '2026-02-28', 'Releve_Z9300_Fevrier_2026.pdf'],
            'février bissextile' => ['2024-02-01', '2024-02-29', 'Releve_Z9300_Fevrier_2024.pdf'],
            'janvier, aux bornes de l\'année' => ['2025-01-01', '2025-01-31', 'Releve_Z9300_Janvier_2025.pdf'],
            'décembre, aux bornes de l\'année' => ['2025-12-01', '2025-12-31', 'Releve_Z9300_Decembre_2025.pdf'],
        ];
    }

    #[DataProvider('periodes')]
    public function test_un_mois_exact_porte_le_nom_de_ce_mois(string $debut, string $fin, string $attendu): void
    {
        $this->assertSame($attendu, PeriodesReleve::nomDeFichier($this->dossier(), $debut, $fin));
    }

    public static function periodesQuiNeSontPasUnMois(): array
    {
        return [
            'une année entière' => ['2026-01-01', '2026-12-31'],
            'un mois amputé d\'un jour' => ['2026-07-01', '2026-07-30'],
            'un mois commencé le 2' => ['2026-07-02', '2026-07-31'],
            'deux mois' => ['2026-07-01', '2026-08-31'],
            'une seule borne' => ['2026-07-01', null],
            'aucune borne' => [null, null],
            'une date illisible' => ['pas-une-date', '2026-07-31'],
        ];
    }

    #[DataProvider('periodesQuiNeSontPasUnMois')]
    public function test_toute_autre_periode_garde_le_nom_date_du_jour(?string $debut, ?string $fin): void
    {
        $nom = PeriodesReleve::nomDeFichier($this->dossier(), $debut, $fin);

        $this->assertStringContainsString(now()->format('Y-m-d'), $nom);
        $this->assertStringNotContainsString('Juillet', $nom);
    }

    public function test_le_nom_du_mois_reste_en_francais_dans_les_trois_langues(): void
    {
        // Le fichier atterrit dans le dossier de téléchargements à côté de ceux
        // que l'ancienne application a déposés. Il s'y trie avec eux.
        foreach (['fr', 'en', 'ar'] as $langue) {
            $this->app->setLocale($langue);

            $this->assertSame(
                'Releve_Z9300_Aout_2026.pdf',
                PeriodesReleve::nomDeFichier($this->dossier(), '2026-08-01', '2026-08-31'),
                "langue : {$langue}",
            );
        }
    }

    // ---- Les mois proposés -------------------------------------------------

    public function test_seuls_les_mois_porteurs_d_un_mouvement_sont_proposes(): void
    {
        $investisseur = $this->dossierEnBase();
        $compte = $this->compte($investisseur);

        // Un achat en mars, une écriture en mai. Avril ne porte rien.
        $this->achat($compte, '2026-03-10');
        $this->ecriture($compte, '2026-05-04');
        $this->ecriture($compte, '2026-05-27');

        $periodes = PeriodesReleve::pour($investisseur);

        $this->assertSame(['2026-05-01', '2026-03-01'], $periodes->pluck('debut')->all());
        $this->assertSame([2, 1], $periodes->pluck('mouvements')->all());
    }

    public function test_les_mois_vont_du_plus_recent_au_plus_ancien(): void
    {
        $investisseur = $this->dossierEnBase();
        $compte = $this->compte($investisseur);

        foreach (['2025-11-02', '2026-01-15', '2025-12-20'] as $date) {
            $this->achat($compte, $date);
        }

        $this->assertSame(
            ['2026-01-01', '2025-12-01', '2025-11-01'],
            PeriodesReleve::pour($investisseur)->pluck('debut')->all(),
        );
    }

    public function test_la_derniere_borne_est_le_dernier_jour_du_mois(): void
    {
        $investisseur = $this->dossierEnBase();
        $this->achat($this->compte($investisseur), '2026-02-10');

        $periode = PeriodesReleve::pour($investisseur)->first();

        $this->assertSame('2026-02-28', $periode['fin']);
        $this->assertSame('Releve_Z9300_Fevrier_2026.pdf', $periode['fichier']);
    }

    public function test_un_dossier_sans_compte_ne_propose_rien(): void
    {
        $this->assertTrue(PeriodesReleve::pour($this->dossierEnBase())->isEmpty());
    }

    // ---- La promesse tenue de bout en bout ---------------------------------

    public function test_le_telechargement_porte_le_nom_que_la_liste_annonce(): void
    {
        [$investisseur, $connexion] = $this->dossierAvecAcces();
        $this->achat($this->compte($investisseur), '2026-07-10');

        $periode = PeriodesReleve::pour($investisseur)->first();

        $reponse = $this->actingAs($connexion)->get(route('portail.releve', [
            'date_debut' => $periode['debut'],
            'date_fin' => $periode['fin'],
            'telecharger' => 1,
        ]));

        $reponse->assertOk();
        $this->assertStringContainsString(
            $periode['fichier'],
            (string) $reponse->headers->get('content-disposition'),
        );
    }

    public function test_l_espace_investisseur_liste_ses_releves(): void
    {
        [$investisseur, $connexion] = $this->dossierAvecAcces();
        $this->achat($this->compte($investisseur), '2026-07-10');

        $this->actingAs($connexion)
            ->get(route('portail.releves.index'))
            ->assertOk()
            ->assertSee('Juillet 2026')
            ->assertSee('Releve_Z9300_Juillet_2026.pdf');
    }

    public function test_la_fiche_du_gestionnaire_annonce_les_releves_mensuels(): void
    {
        $investisseur = $this->dossierEnBase();
        $this->achat($this->compte($investisseur), '2026-07-10');

        // Le bloc arrive replié : c'est son intitulé et son compte qui doivent
        // se lire, pas le tableau.
        $this->actingAs($this->administrateur())
            ->get(route('investisseurs.show', $investisseur))
            ->assertOk()
            ->assertSee('Relevés mensuels')
            ->assertSee('(1)');
    }

    public function test_une_date_illisible_dans_l_adresse_ne_casse_pas_le_releve(): void
    {
        [, $connexion] = $this->dossierAvecAcces();

        // Les bornes viennent d'un champ date ou d'un lien ; une adresse se
        // modifie pourtant à la main, et cela rendait une page d'erreur.
        $this->actingAs($connexion)
            ->get(route('portail.releve', ['date_debut' => 'pas-une-date', 'date_fin' => '2026-07-31']))
            ->assertRedirect();
    }

    // ---- Les pièces du décor ----------------------------------------------

    /** Un dossier en mémoire : suffisant pour nommer un fichier. */
    private function dossier(): Investisseur
    {
        return new Investisseur(['identifiant_externe' => 'Z9300']);
    }

    private function dossierEnBase(): Investisseur
    {
        $gestionnaire = \App\Models\Gestionnaire::orderBy('id')->firstOrFail();

        return Investisseur::create([
            'identifiant_externe' => 'Z9300',
            'type_personne' => 'physique',
            'nom' => 'Essai',
            'prenom' => 'Relevés',
            'statut' => 'actif',
            'gestionnaire_id' => $gestionnaire->id,
        ]);
    }

    /** @return array{0: Investisseur, 1: \App\Models\User} */
    private function dossierAvecAcces(): array
    {
        $investisseur = $this->dossierEnBase();

        $connexion = \App\Models\User::create([
            'nom' => 'Essai',
            'prenom' => 'Relevés',
            'telephone' => '+221779300930',
            'password' => \Illuminate\Support\Facades\Hash::make('quelquechose'),
            'role' => 'investisseur',
            'actif' => true,
            'doit_changer_mot_de_passe' => false,
        ]);

        $investisseur->update(['user_id' => $connexion->id]);

        return [$investisseur->fresh(), $connexion];
    }

    private function administrateur(): \App\Models\User
    {
        return \App\Models\User::create([
            'nom' => 'Essai',
            'prenom' => 'Administration',
            'email' => 'essai.releves@local.test',
            'password' => \Illuminate\Support\Facades\Hash::make('quelquechose'),
            'role' => 'administrateur',
            'actif' => true,
            'doit_changer_mot_de_passe' => false,
        ]);
    }

    private function compte(Investisseur $investisseur): int
    {
        return DB::table('comptes_investissement')->insertGetId([
            'investisseur_id' => $investisseur->id,
            'categorie' => 'commercial',
            'numero_compte' => 'Z9300-COM',
            'date_ouverture' => '2025-11-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function achat(int $compte, string $date): void
    {
        DB::table('achats_actions')->insert([
            'compte_id' => $compte,
            'numero_achat' => 'Z9300-' . str_replace('-', '', $date),
            'date_achat' => $date,
            'type_achat' => 'rajout',
            'nombre_actions' => 1,
            'prix_unitaire' => 25000,
            'montant' => 25000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function ecriture(int $compte, string $date): void
    {
        DB::table('ecritures_compte_financier')->insert([
            'compte_id' => $compte,
            'type_ecriture' => 'dividende',
            'montant' => 1000,
            'solde_apres' => 1000,
            'date_ecriture' => $date,
            // Cette table n'a pas d'updated_at : une écriture ne se modifie pas.
            'created_at' => now(),
        ]);
    }
}
