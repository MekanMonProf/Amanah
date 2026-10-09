<?php

namespace Tests\Feature;

use App\Models\Investisseur;
use App\Support\DocumentsDuDossier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Le catalogue des documents d'un dossier, et le nom qu'il annonce.
 *
 * Le nom est le point fragile. L'écran l'affiche avant que le PDF existe, et
 * chaque contrôleur le construit de son côté, avec ses propres préfixes. Si les
 * deux divergent, la liste ment sans que rien ne casse — c'est exactement ce
 * qui était arrivé aux relevés. Le dernier test ici compare, pour chaque
 * famille, le nom annoncé et le nom réellement livré par la route.
 */
class DocumentsDuDossierTest extends TestCase
{
    private Investisseur $dossier;
    private Investisseur $defunt;
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

        $this->defunt = $this->dossierEnBase('Z9401');
        $this->dossier = $this->dossierEnBase('Z9400');
        $this->compte = $this->compte($this->dossier);
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    public function test_un_dossier_sans_compte_ne_porte_aucun_document(): void
    {
        $this->assertTrue(DocumentsDuDossier::pour($this->dossierEnBase('Z9402'))->isEmpty());
    }

    public function test_chaque_nature_d_operation_donne_son_document(): void
    {
        $this->peuplerToutesLesFamilles();

        $familles = DocumentsDuDossier::pour($this->dossier)
            ->groupBy('famille')
            ->map->count()
            ->all();

        $this->assertSame([
            'achat' => 1,
            'reinvestissement' => 1,
            'present' => 1,
            'radiation' => 1,
            'succession' => 1,
            'paiement' => 1,
            'deces' => 1,
            // Un reçu par écriture : les deux paiements et le dividende.
            'recu' => 3,
        ], $familles);
    }

    public function test_les_familles_viennent_dans_l_ordre_declare(): void
    {
        $this->peuplerToutesLesFamilles();

        $ordre = DocumentsDuDossier::pour($this->dossier)->pluck('famille')->unique()->values()->all();

        $this->assertSame(array_values(array_intersect(array_keys(DocumentsDuDossier::FAMILLES), $ordre)), $ordre);
    }

    public function test_un_paiement_de_succession_ne_donne_pas_aussi_l_attestation_ordinaire(): void
    {
        $this->ecriture('paiement', '2026-05-10', reference: ['succession_deces', $this->defunt->id]);

        $familles = DocumentsDuDossier::pour($this->dossier)->pluck('famille')->all();

        // L'attestation de succession dit tout ce que dirait celle de versement,
        // en nommant de surcroît le défunt : les deux côte à côte n'offriraient
        // qu'un choix sans objet.
        $this->assertContains('deces', $familles);
        $this->assertNotContains('paiement', $familles);
    }

    public function test_une_ecriture_ordinaire_ne_donne_qu_un_recu(): void
    {
        $this->ecriture('dividende', '2026-04-30');

        $this->assertSame(['recu'], DocumentsDuDossier::pour($this->dossier)->pluck('famille')->all());
    }

    // ---- La promesse tenue de bout en bout ---------------------------------

    public function test_le_nom_annonce_est_celui_que_la_route_livre(): void
    {
        $this->peuplerToutesLesFamilles();

        $personnel = $this->administrateur();

        foreach (DocumentsDuDossier::pour($this->dossier) as $document) {
            $reponse = $this->actingAs($personnel)->get(route(
                $document['route'],
                array_merge($document['parametres'], ['telecharger' => 1]),
            ));

            $reponse->assertOk();

            $this->assertSame(
                'attachment; filename=' . $document['fichier'],
                $reponse->headers->get('content-disposition'),
                "famille : {$document['famille']}",
            );
        }
    }

    public function test_l_espace_investisseur_liste_ses_documents(): void
    {
        $this->achat('initial', '2025-11-05');

        $connexion = \App\Models\User::create([
            'nom' => 'Essai',
            'prenom' => 'Documents',
            'telephone' => '+221779400940',
            'password' => Hash::make('quelquechose'),
            'role' => 'investisseur',
            'actif' => true,
            'doit_changer_mot_de_passe' => false,
        ]);
        $this->dossier->update(['user_id' => $connexion->id]);

        $this->actingAs($connexion)
            ->get(route('portail.documents.index'))
            ->assertOk()
            ->assertSee("Attestations d'achat")
            ->assertSee('Attestation_Achat_ACH-Z20251105.pdf');
    }

    public function test_la_fiche_du_gestionnaire_annonce_les_documents(): void
    {
        $this->achat('initial', '2025-11-05');

        // Le bloc arrive replié : c'est son intitulé et son compte qui se lisent.
        $this->actingAs($this->administrateur())
            ->get(route('investisseurs.show', $this->dossier))
            ->assertOk()
            ->assertSee('Attestations et reçus');
    }

    // ---- Les pièces du décor ----------------------------------------------

    /** Une opération de chaque nature, pour couvrir les huit familles. */
    private function peuplerToutesLesFamilles(): void
    {
        $this->achat('initial', '2025-11-05');
        $this->achat('benefice', '2026-01-31');
        $this->achat('rajout', '2026-02-14', offertPar: $this->defunt->id);

        $this->radiation('RAD-Z9400-01', '2026-03-20');
        $this->radiation('RAD-SUCC-Z9400-01', '2026-03-21');

        $this->ecriture('paiement', '2026-04-10');
        $this->ecriture('paiement', '2026-05-10', reference: ['succession_deces', $this->defunt->id]);
        $this->ecriture('dividende', '2026-06-30');
    }

    private function dossierEnBase(string $identifiant): Investisseur
    {
        $gestionnaire = \App\Models\Gestionnaire::orderBy('id')->firstOrFail();

        return Investisseur::create([
            'identifiant_externe' => $identifiant,
            'type_personne' => 'physique',
            'nom' => 'Essai',
            'prenom' => 'Documents',
            'statut' => 'actif',
            'gestionnaire_id' => $gestionnaire->id,
        ]);
    }

    private function administrateur(): \App\Models\User
    {
        return \App\Models\User::create([
            'nom' => 'Essai',
            'prenom' => 'Administration',
            'email' => 'essai.documents@local.test',
            'password' => Hash::make('quelquechose'),
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
            'numero_compte' => $investisseur->identifiant_externe . '-COM',
            'date_ouverture' => '2025-11-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function achat(string $type, string $date, ?int $offertPar = null): void
    {
        DB::table('achats_actions')->insert([
            'compte_id' => $this->compte,
            'numero_achat' => 'ACH-Z' . str_replace('-', '', $date),
            'date_achat' => $date,
            'type_achat' => $type,
            'nombre_actions' => 2,
            'prix_unitaire' => 25000,
            'montant' => 50000,
            'offert_par_investisseur_id' => $offertPar,
            'type_present' => $offertPar ? 'honneur' : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function radiation(string $numero, string $date): void
    {
        DB::table('radiations')->insert([
            'compte_id' => $this->compte,
            'numero_radiation' => $numero,
            'date_radiation' => $date,
            'nombre_actions_radiees' => 1,
            'prix_unitaire_action' => 25000,
            'montant_total' => 25000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @param array{0: string, 1: int}|null $reference */
    private function ecriture(string $type, string $date, ?array $reference = null): void
    {
        DB::table('ecritures_compte_financier')->insert([
            'compte_id' => $this->compte,
            'type_ecriture' => $type,
            'montant' => $type === 'paiement' ? -25000 : 1000,
            'solde_apres' => 0,
            'date_ecriture' => $date,
            'reference_type' => $reference[0] ?? null,
            'reference_id' => $reference[1] ?? null,
            'created_at' => now(),
        ]);
    }
}
