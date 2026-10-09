<?php

namespace Tests\Feature;

use App\Livewire\Gestionnaires\GestionnaireShow;
use App\Livewire\Investisseurs\InvestisseurShow;
use App\Models\Gestionnaire;
use App\Models\HistoriqueAffectation;
use App\Models\Investisseur;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** La fiche d'un gestionnaire : son portefeuille, ses transferts, ses actions. */
class FicheGestionnaireTest extends TestCase
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

    // ---- Le repli, et son défaut ------------------------------------------

    /**
     * Le bug que ce test ferme : la vue affichait ces blocs repliés tandis que
     * basculerBloc les supposait ouverts. Le premier clic écrivait « replié »
     * sur un bloc déjà replié, et il en fallait deux pour l'ouvrir.
     */
    public function test_un_seul_clic_ouvre_un_bloc_replie_par_defaut(): void
    {
        $fiche = new GestionnaireShow;
        $fiche->blocsReplies = [];

        $this->assertTrue($fiche->estReplie('portefeuille'), 'replié au départ');

        $fiche->basculerBloc('portefeuille');

        $this->assertFalse($fiche->estReplie('portefeuille'), 'un clic doit suffire à ouvrir');

        $fiche->basculerBloc('portefeuille');

        $this->assertTrue($fiche->estReplie('portefeuille'), 'le clic suivant referme');
    }

    public function test_la_fiche_investisseur_connait_le_meme_defaut(): void
    {
        $fiche = new InvestisseurShow;
        $fiche->blocsReplies = [];

        // Les relevés et les documents arrivent repliés, les historiques ouverts.
        $this->assertTrue($fiche->estReplie('releves'));
        $this->assertTrue($fiche->estReplie('documents'));
        $this->assertFalse($fiche->estReplie('achats'));

        $fiche->basculerBloc('releves');
        $fiche->basculerBloc('achats');

        $this->assertFalse($fiche->estReplie('releves'));
        $this->assertTrue($fiche->estReplie('achats'));
    }

    // ---- Ce que la fiche montre -------------------------------------------

    public function test_la_fiche_annonce_le_volume_du_portefeuille(): void
    {
        $gestionnaire = $this->gestionnaire('fiche.volume@local.test');
        $this->dossier('Z9500', $gestionnaire);
        $this->dossier('Z9501', $gestionnaire, statut: 'inactif');

        $this->actingAs($this->administrateur())
            ->get(route('gestionnaires.show', $gestionnaire))
            ->assertOk()
            ->assertSee('Essai Fiche')
            // Deux dossiers suivis, dont un seul actif.
            ->assertSee('Portefeuille')
            ->assertSee('1 dossier(s) actif(s)');
    }

    public function test_le_portefeuille_nomme_les_dossiers(): void
    {
        $gestionnaire = $this->gestionnaire('fiche.portefeuille@local.test');
        $this->dossier('Z9502', $gestionnaire);

        $this->withSession(['amanah.blocs_replies_gestionnaire' => ['portefeuille' => false]])
            ->actingAs($this->administrateur())
            ->get(route('gestionnaires.show', $gestionnaire))
            ->assertOk()
            ->assertSee('Z9502')
            ->assertSee('Voir le dossier');
    }

    public function test_les_transferts_disent_le_sens(): void
    {
        $cedant = $this->gestionnaire('fiche.cedant@local.test');
        $repreneur = $this->gestionnaire('fiche.repreneur@local.test');
        $dossier = $this->dossier('Z9503', $repreneur);

        HistoriqueAffectation::create([
            'investisseur_id' => $dossier->id,
            'ancien_gestionnaire_id' => $cedant->id,
            'nouveau_gestionnaire_id' => $repreneur->id,
            'date_transfert' => '2026-05-04',
            'motif' => 'Rééquilibrage',
        ]);

        $personnel = $this->administrateur();
        $session = ['amanah.blocs_replies_gestionnaire' => ['transferts' => false]];

        // Le même transfert se lit dans les deux sens, selon la fiche ouverte.
        $this->withSession($session)->actingAs($personnel)
            ->get(route('gestionnaires.show', $repreneur))
            ->assertOk()
            ->assertSee('Reçu de');

        $this->withSession($session)->actingAs($personnel)
            ->get(route('gestionnaires.show', $cedant))
            ->assertOk()
            ->assertSee('Cédé à');
    }

    public function test_une_fiche_sans_dossier_le_dit(): void
    {
        $this->actingAs($this->administrateur())
            ->get(route('gestionnaires.show', $this->gestionnaire('fiche.vide@local.test')))
            ->assertOk()
            ->assertSee('Aucun dossier ne lui est assigné.');
    }

    public function test_le_role_lecture_n_ouvre_pas_la_fiche(): void
    {
        // La route exige le module gestionnaires en écriture, comme la liste :
        // la fiche porte les mêmes actions.
        $lecture = User::create([
            'nom' => 'Essai',
            'prenom' => 'Lecture',
            'email' => 'fiche.lecture@local.test',
            'password' => Hash::make('quelquechose'),
            'role' => 'lecture',
            'actif' => true,
            'doit_changer_mot_de_passe' => false,
        ]);

        $this->actingAs($lecture)
            ->get(route('gestionnaires.show', $this->gestionnaire('fiche.interdite@local.test')))
            ->assertForbidden();
    }

    // ---- Les pièces du décor ----------------------------------------------

    private function gestionnaire(string $email): Gestionnaire
    {
        $user = User::create([
            'nom' => 'Essai',
            'prenom' => 'Fiche',
            'email' => $email,
            'password' => Hash::make('quelquechose'),
            'role' => 'gestionnaire',
            'actif' => true,
            'doit_changer_mot_de_passe' => false,
        ]);

        return Gestionnaire::create(['user_id' => $user->id, 'actif' => true]);
    }

    private function dossier(string $identifiant, Gestionnaire $gestionnaire, string $statut = 'actif'): Investisseur
    {
        return Investisseur::create([
            'identifiant_externe' => $identifiant,
            'type_personne' => 'physique',
            'nom' => 'Essai',
            'prenom' => 'Dossier',
            'statut' => $statut,
            'gestionnaire_id' => $gestionnaire->id,
        ]);
    }

    private function administrateur(): User
    {
        return User::create([
            'nom' => 'Essai',
            'prenom' => 'Administration',
            'email' => 'fiche.administration@local.test',
            'password' => Hash::make('quelquechose'),
            'role' => 'administrateur',
            'actif' => true,
            'doit_changer_mot_de_passe' => false,
        ]);
    }
}
