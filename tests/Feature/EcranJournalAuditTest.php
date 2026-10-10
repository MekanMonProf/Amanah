<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Le bandeau du journal d'audit.
 *
 * Il répond à une question de volume — combien d'actions, sur quelle étendue —
 * que vingt-cinq lignes paginées ne donnaient pas. Sa valeur tient à une
 * chose : les chiffres décrivent ce qu'on regarde, filtres compris, et non le
 * journal entier. Un bandeau qui annoncerait toujours le total serait pire que
 * pas de bandeau, puisqu'il répondrait à côté.
 */
class EcranJournalAuditTest extends TestCase
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

    public function test_le_bandeau_compte_ce_que_le_journal_contient(): void
    {
        $this->actingAs($this->administrateur())
            ->get(route('audit.index'))
            ->assertOk()
            ->assertSee('Actions tracées')
            ->assertSee('Personnes concernées')
            ->assertSee('Tout ce qui a été tracé');
    }

    public function test_les_chiffres_suivent_le_filtre(): void
    {
        $personnel = $this->administrateur();

        $total = AuditLog::count();
        $this->tracer('2026-02-01 08:00:00');
        $this->tracer('2026-04-15 09:30:00');

        $composant = Livewire::actingAs($personnel)->test(\App\Livewire\JournalAudit::class);

        $this->assertSame($total + 2, $composant->instance()->position['actions']);

        $composant->set('filtreAction', self::ACTION_D_ESSAI);

        $this->assertSame(2, $composant->instance()->position['actions']);
        $composant->assertSee('Ce que le filtre ramène');
    }

    public function test_le_bandeau_borne_la_periode_regardee(): void
    {
        $this->tracer('2026-02-01 08:00:00');
        $this->tracer('2026-04-15 09:30:00');

        $position = Livewire::actingAs($this->administrateur())
            ->test(\App\Livewire\JournalAudit::class)
            ->set('filtreAction', self::ACTION_D_ESSAI)
            ->instance()
            ->position;

        // Les bornes sont celles du filtre, pas celles du journal entier.
        $this->assertSame('2026-02-01 08:00:00', (string) $position['premiere']);
        $this->assertSame('2026-04-15 09:30:00', (string) $position['derniere']);
    }

    /**
     * Une action qui n'existe pas dans le jeu de répétition : le filtre ne
     * ramènera donc que les lignes posées ici, quoi que la base contienne.
     */
    private const ACTION_D_ESSAI = 'essai_bandeau_audit';

    private function tracer(string $horodatage): void
    {
        DB::table('audit_logs')->insert([
            'user_id' => null,
            'action' => self::ACTION_D_ESSAI,
            'entite' => 'essai',
            'created_at' => $horodatage,
        ]);
    }

    private function administrateur(): User
    {
        return User::create([
            'nom' => 'Essai',
            'prenom' => 'Administration',
            'email' => 'ecran.audit@local.test',
            'password' => Hash::make('quelquechose'),
            'role' => 'administrateur',
            'actif' => true,
            'doit_changer_mot_de_passe' => false,
        ]);
    }
}
