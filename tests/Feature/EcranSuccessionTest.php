<?php

namespace Tests\Feature;

use App\Models\Investisseur;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * L'écran de succession, une fois mis à la forme des autres fiches.
 *
 * Il portait le déroulé du règlement et rien d'autre : ni ce que la succession
 * met en jeu, ni les pièces qu'elle produit. Les deux se lisaient ailleurs, ou
 * pas du tout.
 */
class EcranSuccessionTest extends TestCase
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

    public function test_le_bandeau_dit_ce_que_la_succession_met_en_jeu(): void
    {
        $defunt = $this->defunt('Z9600');
        $compte = $this->compte($defunt, 'commercial');
        $this->achat($compte, 4);
        $this->ecriture($compte, 'dividende', 12000);

        $this->actingAs($this->administrateur())
            ->get(route('successions.gerer', $defunt))
            ->assertOk()
            ->assertSee('Ce qui est en jeu')
            ->assertSee('Actions commerciales')
            // Par le formateur maison : le séparateur de milliers est une
            // espace fine insécable, qu'une chaîne écrite à la main rate.
            ->assertSee(\App\Support\Montant::avecDevise(12000), false)
            ->assertSee(\App\Support\Montant::format(4));
    }

    public function test_l_etat_du_dossier_se_lit_en_haut(): void
    {
        $aRegler = $this->defunt('Z9601');
        $reglee = $this->defunt('Z9602', reglee: true);

        $personnel = $this->administrateur();

        $this->actingAs($personnel)->get(route('successions.gerer', $aRegler))
            ->assertOk()->assertSee('À régler');

        $this->actingAs($personnel)->get(route('successions.gerer', $reglee))
            ->assertOk()->assertSee('Réglée');
    }

    public function test_les_pieces_de_la_succession_sont_a_portee(): void
    {
        $defunt = $this->defunt('Z9603', reglee: true);
        $compte = $this->compte($defunt, 'commercial');

        $this->radiationDeSuccession($compte);
        $this->ecriture($compte, 'paiement', -50000, ['succession_deces', $defunt->id]);

        $this->withSession(['amanah.blocs_replies_succession' => ['documents' => false]])
            ->actingAs($this->administrateur())
            ->get(route('successions.gerer', $defunt))
            ->assertOk()
            ->assertSee('Pièces de la succession')
            ->assertSee('Attestations de liquidation de succession')
            ->assertSee('Attestations de versement à la succession');
    }

    public function test_seules_les_pieces_de_la_succession_y_figurent(): void
    {
        $defunt = $this->defunt('Z9604', reglee: true);
        $compte = $this->compte($defunt, 'commercial');

        // Un achat ordinaire produit son attestation, qui n'a rien à faire ici :
        // elle se consulte sur la fiche, parmi les documents du dossier.
        $this->achat($compte, 2);
        $this->radiationDeSuccession($compte);

        $this->withSession(['amanah.blocs_replies_succession' => ['documents' => false]])
            ->actingAs($this->administrateur())
            ->get(route('successions.gerer', $defunt))
            ->assertOk()
            ->assertSee('Attestations de liquidation de succession')
            ->assertDontSee("Attestations d'achat");
    }

    public function test_sans_reglement_le_bloc_des_pieces_n_existe_pas(): void
    {
        $this->actingAs($this->administrateur())
            ->get(route('successions.gerer', $this->defunt('Z9605')))
            ->assertOk()
            ->assertDontSee('Pièces de la succession');
    }

    // ---- Les pièces du décor ----------------------------------------------

    private function defunt(string $identifiant, bool $reglee = false): Investisseur
    {
        $gestionnaire = \App\Models\Gestionnaire::orderBy('id')->firstOrFail();

        return Investisseur::create([
            'identifiant_externe' => $identifiant,
            'type_personne' => 'physique',
            'nom' => 'Essai',
            'prenom' => 'Succession',
            'statut' => 'decede',
            'date_deces' => '2026-06-08',
            'succession_reglee' => $reglee,
            'gestionnaire_id' => $gestionnaire->id,
        ]);
    }

    private function compte(Investisseur $investisseur, string $categorie): int
    {
        return DB::table('comptes_investissement')->insertGetId([
            'investisseur_id' => $investisseur->id,
            'categorie' => $categorie,
            'numero_compte' => $investisseur->identifiant_externe . '-' . strtoupper(substr($categorie, 0, 3)),
            'date_ouverture' => '2025-11-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function achat(int $compte, int $actions): void
    {
        DB::table('achats_actions')->insert([
            'compte_id' => $compte,
            'numero_achat' => 'ACH-S' . $compte,
            'date_achat' => '2026-01-10',
            'type_achat' => 'initial',
            'nombre_actions' => $actions,
            'prix_unitaire' => 25000,
            'montant' => 25000 * $actions,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function radiationDeSuccession(int $compte): void
    {
        DB::table('radiations')->insert([
            'compte_id' => $compte,
            // Le préfixe est ce qui distingue une liquidation d'une radiation
            // ordinaire — voir Radiation::estUneSuccession().
            'numero_radiation' => \App\Models\Radiation::PREFIXE_SUCCESSION . $compte,
            'date_radiation' => '2026-06-20',
            'nombre_actions_radiees' => 2,
            'prix_unitaire_action' => 25000,
            'montant_total' => 50000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @param array{0: string, 1: int}|null $reference */
    private function ecriture(int $compte, string $type, float $montant, ?array $reference = null): void
    {
        DB::table('ecritures_compte_financier')->insert([
            'compte_id' => $compte,
            'type_ecriture' => $type,
            'montant' => $montant,
            'solde_apres' => max($montant, 0),
            'date_ecriture' => '2026-06-25',
            'reference_type' => $reference[0] ?? null,
            'reference_id' => $reference[1] ?? null,
            'created_at' => now(),
        ]);
    }

    private function administrateur(): User
    {
        return User::create([
            'nom' => 'Essai',
            'prenom' => 'Administration',
            'email' => 'ecran.succession@local.test',
            'password' => Hash::make('quelquechose'),
            'role' => 'administrateur',
            'actif' => true,
            'doit_changer_mot_de_passe' => false,
        ]);
    }
}
