<?php

namespace Tests\Feature;

use App\Models\CompteInvestissement;
use App\Models\Don;
use App\Models\Investisseur;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** L'export des dons, qui manquait là où les trois autres historiques l'avaient. */
class ExportDonsTest extends TestCase
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

    private function admin(): User
    {
        return User::where('role', 'administrateur')->orderBy('id')->firstOrFail();
    }

    /** Deux comptes à nous, et un don d'actions de l'un vers l'autre. */
    private function unDon(): array
    {
        $comptes = [];

        foreach (['Z9300', 'Z9301'] as $identifiant) {
            $investisseur = Investisseur::create([
                'identifiant_externe' => $identifiant,
                'type_personne' => 'physique',
                'nom' => 'Essai',
                'prenom' => 'Don ' . $identifiant,
                'statut' => 'actif',
            ]);

            $comptes[] = $investisseur->compteOuCree('commercial');
        }

        [$source, $destinataire] = $comptes;

        $don = Don::create([
            'compte_source_id' => $source->id,
            'compte_destinataire_id' => $destinataire->id,
            'type_don' => 'actions',
            'nombre_actions' => 7,
            'prix_unitaire_action' => 25000,
            'date_don' => '2026-05-12',
            'motif' => 'Essai de transmission',
            'created_by' => $this->admin()->id,
        ]);

        return [$source, $destinataire, $don];
    }

    public function test_le_csv_porte_le_don_des_deux_cotes(): void
    {
        [$source, $destinataire] = $this->unDon();

        $donne = $this->actingAs($this->admin())
            ->get(route('export.dons.csv', ['compte' => $source->id]))
            ->assertOk()->streamedContent();

        $this->assertStringContainsString('Donné', $donne);
        $this->assertStringContainsString('Essai de transmission', $donne);

        // Le même don, vu de l'autre compte, doit se lire comme reçu.
        $recu = $this->actingAs($this->admin())
            ->get(route('export.dons.csv', ['compte' => $destinataire->id]))
            ->assertOk()->streamedContent();

        $this->assertStringContainsString('Reçu', $recu);
    }

    public function test_le_csv_commence_par_la_marque_d_ordre_des_octets(): void
    {
        [$source] = $this->unDon();

        $contenu = $this->actingAs($this->admin())
            ->get(route('export.dons.csv', ['compte' => $source->id]))
            ->assertOk()->streamedContent();

        // Sans elle, Excel ouvre le fichier en ANSI et les accents tombent.
        $this->assertStringStartsWith("\xEF\xBB\xBF", $contenu);
    }

    public function test_les_filtres_de_l_ecran_suivent_dans_l_export(): void
    {
        [$source] = $this->unDon();
        $admin = $this->admin();

        $horsPeriode = $this->actingAs($admin)
            ->get(route('export.dons.csv', ['compte' => $source->id, 'date_debut' => '2026-06-01']))
            ->assertOk()->streamedContent();

        $this->assertStringNotContainsString('Essai de transmission', $horsPeriode);

        // Vu de la source, le don est émis : le filtre « reçus » doit le taire.
        $mauvaisSens = $this->actingAs($admin)
            ->get(route('export.dons.csv', ['compte' => $source->id, 'sens' => 'recus']))
            ->assertOk()->streamedContent();

        $this->assertStringNotContainsString('Essai de transmission', $mauvaisSens);
    }

    public function test_le_pdf_se_rend(): void
    {
        [$source] = $this->unDon();

        $reponse = $this->actingAs($this->admin())
            ->get(route('export.dons.pdf', ['compte' => $source->id]))
            ->assertOk();

        $this->assertStringContainsString('application/pdf', $reponse->headers->get('content-type'));
    }

    public function test_un_compte_sans_don_rend_un_pdf_qui_le_dit(): void
    {
        $investisseur = Investisseur::create([
            'identifiant_externe' => 'Z9302',
            'type_personne' => 'physique',
            'nom' => 'Essai',
            'prenom' => 'Sans don',
            'statut' => 'actif',
        ]);

        // Une page muette laisse croire à une erreur d'édition.
        $this->actingAs($this->admin())
            ->get(route('export.dons.pdf', ['compte' => $investisseur->compteOuCree('commercial')->id]))
            ->assertOk();
    }

    public function test_l_historique_des_dons_propose_les_deux_exports(): void
    {
        // Il faut un compte qui ait vraiment un don : le bloc ne s'affiche pas
        // sur un compte qui n'en a aucun, et c'est voulu.
        [$source] = $this->unDon();

        $contenu = $this->actingAs($this->admin())
            ->get('/investisseurs/' . $source->investisseur_id)->assertOk()->getContent();

        foreach (['export.dons.csv', 'export.dons.pdf'] as $route) {
            $this->assertStringContainsString(
                route($route, ['compte' => $source->id], false),
                $contenu,
                "l'historique des dons doit proposer {$route}"
            );
        }
    }

    public function test_un_compte_sans_don_n_affiche_pas_le_bloc(): void
    {
        $investisseur = Investisseur::create([
            'identifiant_externe' => 'Z9303',
            'type_personne' => 'physique',
            'nom' => 'Essai',
            'prenom' => 'Aucun don',
            'statut' => 'actif',
        ]);

        $investisseur->compteOuCree('commercial');

        $this->actingAs($this->admin())
            ->get('/investisseurs/' . $investisseur->id)->assertOk()
            ->assertDontSee(e(__('Historique des dons')), false);
    }
}
