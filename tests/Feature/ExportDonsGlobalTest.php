<?php

namespace Tests\Feature;

use App\Models\Don;
use App\Models\Gestionnaire;
use App\Models\Investisseur;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * L'export global des dons.
 *
 * Les quatre autres exports globaux suivent un chemin unique vers
 * l'investisseur ; un don, lui, relie deux comptes. Un gestionnaire doit donc
 * voir les dons partis de ses dossiers comme ceux qui y sont arrivés — les
 * deux le concernent — et ceux de ses confrères lui restent invisibles. C'est
 * la seule règle neuve de cet export, et ce fichier ne teste qu'elle.
 */
class ExportDonsGlobalTest extends TestCase
{
    private Gestionnaire $mariama;
    private Gestionnaire $ousmane;

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

        $this->mariama = $this->gestionnaire('export.dons.mariama@local.test');
        $this->ousmane = $this->gestionnaire('export.dons.ousmane@local.test');
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    public function test_le_personnel_voit_tous_les_dons(): void
    {
        $this->don($this->compte('Z9700', $this->mariama), $this->compte('Z9701', $this->ousmane), '2026-03-01');
        $this->don($this->compte('Z9702', $this->ousmane), $this->compte('Z9703', $this->ousmane), '2026-03-02');

        $csv = $this->exporter($this->administrateur());

        $this->assertStringContainsString('Z9700', $csv);
        $this->assertStringContainsString('Z9702', $csv);
    }

    public function test_un_gestionnaire_voit_les_dons_partis_de_ses_dossiers(): void
    {
        $this->don($this->compte('Z9710', $this->mariama), $this->compte('Z9711', $this->ousmane), '2026-03-01');

        $this->assertStringContainsString('Z9710', $this->exporter($this->compteDe($this->mariama)));
    }

    public function test_un_gestionnaire_voit_les_dons_arrives_dans_ses_dossiers(): void
    {
        // Le bénéficiaire est à lui : le don fait monter un solde qu'il suit.
        $this->don($this->compte('Z9720', $this->ousmane), $this->compte('Z9721', $this->mariama), '2026-03-01');

        $this->assertStringContainsString('Z9721', $this->exporter($this->compteDe($this->mariama)));
    }

    public function test_un_gestionnaire_ne_voit_pas_les_dons_des_autres(): void
    {
        $this->don($this->compte('Z9730', $this->ousmane), $this->compte('Z9731', $this->ousmane), '2026-03-01');

        $csv = $this->exporter($this->compteDe($this->mariama));

        $this->assertStringNotContainsString('Z9730', $csv);
        $this->assertStringNotContainsString('Z9731', $csv);
    }

    public function test_la_periode_borne_l_export(): void
    {
        $source = $this->compte('Z9740', $this->mariama);
        $cible = $this->compte('Z9741', $this->mariama);

        $this->don($source, $cible, '2026-01-15', motif: 'Avant la période');
        $this->don($source, $cible, '2026-06-15', motif: 'Dans la période');

        $csv = $this->exporter($this->administrateur(), ['date_debut' => '2026-05-01', 'date_fin' => '2026-07-31']);

        $this->assertStringContainsString('Dans la période', $csv);
        $this->assertStringNotContainsString('Avant la période', $csv);
    }

    public function test_l_export_nomme_les_deux_parties(): void
    {
        $this->don($this->compte('Z9750', $this->mariama), $this->compte('Z9751', $this->ousmane), '2026-03-01');

        $csv = $this->exporter($this->administrateur());

        // Pas de colonne « Sens » : elle n'aurait de sens que depuis un compte.
        $this->assertStringContainsString('Donateur', $csv);
        $this->assertStringContainsString('Bénéficiaire', $csv);
        $this->assertStringNotContainsString('Sens', $csv);
    }

    // ---- Les pièces du décor ----------------------------------------------

    private function exporter(User $utilisateur, array $parametres = []): string
    {
        $reponse = $this->actingAs($utilisateur)->get(route('export.dons.global.csv', $parametres));

        $reponse->assertOk();

        return $reponse->streamedContent();
    }

    private function gestionnaire(string $email): Gestionnaire
    {
        $user = User::create([
            'nom' => 'Essai',
            'prenom' => 'Dons',
            'email' => $email,
            'password' => Hash::make('quelquechose'),
            'role' => 'gestionnaire',
            'actif' => true,
            'doit_changer_mot_de_passe' => false,
        ]);

        return Gestionnaire::create(['user_id' => $user->id, 'actif' => true]);
    }

    private function compteDe(Gestionnaire $gestionnaire): User
    {
        return $gestionnaire->user;
    }

    /** Un dossier et son compte, dont le numéro sert de marqueur dans le CSV. */
    private function compte(string $identifiant, Gestionnaire $gestionnaire): int
    {
        $investisseur = Investisseur::create([
            'identifiant_externe' => $identifiant,
            'type_personne' => 'physique',
            'nom' => 'Essai',
            'prenom' => 'Dons',
            'statut' => 'actif',
            'gestionnaire_id' => $gestionnaire->id,
        ]);

        return DB::table('comptes_investissement')->insertGetId([
            'investisseur_id' => $investisseur->id,
            'categorie' => 'commercial',
            'numero_compte' => $identifiant . '-COM',
            'date_ouverture' => '2025-11-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function don(int $source, int $destinataire, string $date, string $motif = 'Essai'): void
    {
        Don::create([
            'compte_source_id' => $source,
            'compte_destinataire_id' => $destinataire,
            'type_don' => 'actions',
            'type_operation' => 'don',
            'nombre_actions' => 1,
            'prix_unitaire_action' => 25000,
            'montant' => 0,
            'date_don' => $date,
            'motif' => $motif,
        ]);
    }

    private function administrateur(): User
    {
        return User::create([
            'nom' => 'Essai',
            'prenom' => 'Administration',
            'email' => 'export.dons.administration@local.test',
            'password' => Hash::make('quelquechose'),
            'role' => 'administrateur',
            'actif' => true,
            'doit_changer_mot_de_passe' => false,
        ]);
    }
}
