<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Montant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * L'écran des dividendes, une fois mis à la forme des autres.
 *
 * Il ne disait que ce qu'on s'apprête à faire. Le bandeau dit maintenant ce
 * qui a déjà été versé — et ce chiffre ne doit compter que l'argent réellement
 * sorti : un dividende calculé puis annulé n'a jamais quitté la caisse.
 */
class EcranDividendesTest extends TestCase
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

    public function test_le_bandeau_dit_ce_qui_a_ete_distribue(): void
    {
        $this->actingAs($this->administrateur())
            ->get(route('dividendes.calculer'))
            ->assertOk()
            ->assertSee('Ce qui a été distribué')
            ->assertSee('Périodes fixées')
            ->assertSee('Total distribué')
            ->assertSee(Montant::avecDevise($this->totalCredite()), false);
    }

    public function test_un_dividende_annule_ne_gonfle_pas_le_total(): void
    {
        $avant = $this->totalCredite();

        $this->dividende(777000, 'annule');

        $this->actingAs($this->administrateur())
            ->get(route('dividendes.calculer'))
            ->assertOk()
            ->assertSee(Montant::avecDevise($avant), false)
            ->assertDontSee(Montant::avecDevise($avant + 777000), false);
    }

    public function test_un_dividende_credite_entre_dans_le_total(): void
    {
        $attendu = $this->totalCredite() + 888000;

        $this->dividende(888000, 'credite');

        $this->actingAs($this->administrateur())
            ->get(route('dividendes.calculer'))
            ->assertOk()
            ->assertSee(Montant::avecDevise($attendu), false);
    }

    public function test_l_historique_se_replie_par_annee(): void
    {
        $html = $this->actingAs($this->administrateur())
            ->get(route('dividendes.calculer'))
            ->assertOk()
            ->assertSee('Historique des barèmes fixés')
            ->getContent();

        // Un état de repli par année, et le bouton qui l'actionne.
        $annees = DB::table('baremes_dividendes')
            ->selectRaw('COUNT(DISTINCT YEAR(periode)) AS n')
            ->value('n');

        $this->assertSame((int) $annees, substr_count($html, 'x-data="{ ouvert: true }"'));
        $this->assertStringContainsString('@click="ouvert = ! ouvert"', $html);
    }

    // ---- Les pièces du décor ----------------------------------------------

    private function totalCredite(): float
    {
        return (float) DB::table('dividendes')->where('statut', 'credite')->sum('montant_calcule');
    }

    private function dividende(float $montant, string $statut): void
    {
        $compte = DB::table('comptes_investissement')->orderBy('id')->value('id');

        DB::table('dividendes')->insert([
            'compte_id' => $compte,
            // Une période que l'historique réel ne porte pas, pour ne rien
            // bousculer d'autre à l'écran.
            'periode' => '2019-01-01',
            'nombre_actions' => 1,
            'benefice_par_action' => $montant,
            'montant_calcule' => $montant,
            'statut' => $statut,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function administrateur(): User
    {
        return User::create([
            'nom' => 'Essai',
            'prenom' => 'Administration',
            'email' => 'ecran.dividendes@local.test',
            'password' => Hash::make('quelquechose'),
            'role' => 'administrateur',
            'actif' => true,
            'doit_changer_mot_de_passe' => false,
        ]);
    }
}
