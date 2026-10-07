<?php

namespace Tests\Feature;

use App\Models\DemandeAcces;
use App\Models\Investisseur;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/** La demande d'accès déposée depuis l'écran de connexion. */
class DemandeAccesTest extends TestCase
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
        RateLimiter::clear('acces|+221771112233|127.0.0.1');
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    /** Un dossier à nous, avec un accès portail par téléphone. */
    private function investisseurJoignable(): Investisseur
    {
        $gestionnaire = \App\Models\Gestionnaire::orderBy('id')->firstOrFail();

        $investisseur = Investisseur::create([
            'identifiant_externe' => 'Z9200',
            'type_personne' => 'physique',
            'nom' => 'Essai',
            'prenom' => 'Accès',
            'statut' => 'actif',
            'telephone' => '+221771112233',
            'gestionnaire_id' => $gestionnaire->id,
        ]);

        $compte = User::create([
            'nom' => 'Essai',
            'prenom' => 'Accès',
            'telephone' => '+221771112233',
            'password' => Hash::make('quelquechose'),
            'role' => 'investisseur',
            'actif' => true,
        ]);

        $investisseur->update(['user_id' => $compte->id]);

        return $investisseur->fresh();
    }

    public function test_un_numero_connu_depose_une_demande(): void
    {
        $investisseur = $this->investisseurJoignable();
        $avant = DemandeAcces::count();

        \Livewire\Volt\Volt::test('pages.auth.forgot-password')
            ->set('identifiant', '77 111 22 33')
            ->call('demander')
            ->assertHasNoErrors();

        $this->assertSame($avant + 1, DemandeAcces::count());

        $demande = DemandeAcces::latest('id')->first();
        $this->assertSame($investisseur->id, $demande->investisseur_id);
        $this->assertSame('nouvelle', $demande->statut);

        // Le numéro est rangé au format international, comme partout ailleurs.
        $this->assertStringStartsWith('+', $demande->telephone);
    }

    public function test_un_numero_inconnu_ne_depose_rien_et_ne_le_dit_pas(): void
    {
        $avant = DemandeAcces::count();

        $inconnu = \Livewire\Volt\Volt::test('pages.auth.forgot-password')
            ->set('identifiant', '+221780000001')
            ->call('demander')
            ->assertHasNoErrors();

        $this->assertSame($avant, DemandeAcces::count(), 'aucune demande ne doit être enregistrée');

        // Le message doit être le même que pour un numéro connu : un écran qui
        // répondrait autrement dirait quels numéros existent en base.
        $attendu = e(__("Si ces informations correspondent à un compte, votre demande est prise en compte. Vous recevrez un message."));
        $inconnu->assertSee($attendu, false);

        $this->investisseurJoignable();

        \Livewire\Volt\Volt::test('pages.auth.forgot-password')
            ->set('identifiant', '+221771112233')
            ->call('demander')
            ->assertSee($attendu, false);
    }

    public function test_insister_ne_cree_pas_de_doublon(): void
    {
        $this->investisseurJoignable();
        $avant = DemandeAcces::count();

        foreach (['77 111 22 33', '00221771112233', '+221771112233'] as $forme) {
            \Livewire\Volt\Volt::test('pages.auth.forgot-password')
                ->set('identifiant', $forme)
                ->call('demander');
        }

        $this->assertSame($avant + 1, DemandeAcces::count(), 'trois essais, une seule demande');
    }

    public function test_un_acces_revoque_ne_se_rouvre_pas_par_ce_chemin(): void
    {
        $investisseur = $this->investisseurJoignable();
        $investisseur->user->update(['actif' => false]);

        $avant = DemandeAcces::count();

        \Livewire\Volt\Volt::test('pages.auth.forgot-password')
            ->set('identifiant', '+221771112233')
            ->call('demander');

        $this->assertSame($avant, DemandeAcces::count());
    }

    public function test_reinitialiser_le_mot_de_passe_clot_la_demande(): void
    {
        $investisseur = $this->investisseurJoignable();
        $demande = DemandeAcces::deposer($investisseur, '+221771112233');

        $admin = User::where('role', 'administrateur')->orderBy('id')->firstOrFail();

        \Livewire\Livewire::actingAs($admin)
            ->test(\App\Livewire\Investisseurs\InvestisseurShow::class, ['investisseur' => $investisseur])
            ->call('reinitialiserMotDePasse');

        $this->assertSame('traitee', $demande->fresh()->statut);
        $this->assertSame($admin->id, $demande->fresh()->traite_par);
    }

    public function test_la_fiche_annonce_la_demande_en_attente(): void
    {
        $investisseur = $this->investisseurJoignable();
        $admin = User::where('role', 'administrateur')->orderBy('id')->firstOrFail();

        $this->actingAs($admin)->get('/investisseurs/' . $investisseur->id)->assertOk()
            ->assertDontSee(e(__('Cet investisseur demande un nouvel accès.')), false);

        DemandeAcces::deposer($investisseur, '+221771112233');

        $this->actingAs($admin)->get('/investisseurs/' . $investisseur->id)->assertOk()
            ->assertSee(e(__('Cet investisseur demande un nouvel accès.')), false);
    }

    public function test_un_email_suit_toujours_la_route_habituelle(): void
    {
        $avant = DemandeAcces::count();

        \Livewire\Volt\Volt::test('pages.auth.forgot-password')
            ->set('identifiant', 'inconnu@example.test')
            ->call('demander')
            ->assertHasNoErrors();

        // Un email ne dépose jamais de demande : il part par le lien de Laravel.
        $this->assertSame($avant, DemandeAcces::count());
    }
}
