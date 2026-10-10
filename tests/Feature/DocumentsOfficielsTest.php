<?php

namespace Tests\Feature;

use App\Livewire\DocumentsOfficiels;
use App\Models\DocumentOfficiel;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Les documents que la direction adresse à tous.
 *
 * Deux règles portent cet écran, et ce fichier ne teste qu'elles : la lecture
 * est ouverte à tout compte connecté — c'est ce qui distingue ces documents des
 * pièces d'un dossier —, et le dépôt est réservé à la direction et à
 * l'administration.
 */
class DocumentsOfficielsTest extends TestCase
{
    /** @var array<int, int> */
    private array $aNettoyer = [];

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

        // Aucun fichier n'atteint le vrai disque.
        Storage::fake(DocumentOfficiel::DISQUE);
    }

    protected function tearDown(): void
    {
        // Livewire::test sort de la transaction : ce qu'un composant écrit
        // survivrait au rollBack. On retire donc à la main ce qu'il a créé.
        if ($this->aNettoyer !== []) {
            DB::table('documents_officiels')->whereIn('id', $this->aNettoyer)->delete();
        }

        DB::rollBack();
        parent::tearDown();
    }

    // ---- La lecture, ouverte à tous ---------------------------------------

    public function test_tout_compte_connecte_voit_la_liste(): void
    {
        foreach (['direction', 'administrateur', 'gestionnaire', 'lecture', 'investisseur'] as $role) {
            $this->actingAs($this->compte($role))
                ->get(route('documents-officiels.index'))
                ->assertOk()
                ->assertSee('Publications de la direction');
        }
    }

    public function test_un_visiteur_sans_compte_est_renvoye_a_la_connexion(): void
    {
        $this->get(route('documents-officiels.index'))->assertRedirect(route('login'));
    }

    public function test_seuls_la_direction_et_l_administration_voient_le_depot(): void
    {
        foreach (['direction', 'administrateur'] as $role) {
            $this->actingAs($this->compte($role))
                ->get(route('documents-officiels.index'))
                ->assertOk()
                ->assertSee('Publier un document');
        }

        foreach (['gestionnaire', 'lecture', 'investisseur'] as $role) {
            $this->actingAs($this->compte($role))
                ->get(route('documents-officiels.index'))
                ->assertOk()
                ->assertDontSee('Publier un document');
        }
    }

    // ---- Le dépôt, réservé ------------------------------------------------

    public function test_la_direction_publie_un_document(): void
    {
        $composant = Livewire::actingAs($this->compte('direction'))
            ->test(DocumentsOfficiels::class)
            ->set('titre', "Rapport annuel d'essai")
            ->set('description', 'Déposé par un test.')
            ->set('fichier', UploadedFile::fake()->create('rapport.pdf', 120, 'application/pdf'))
            ->call('publier')
            ->assertHasNoErrors();

        $document = DocumentOfficiel::where('titre', "Rapport annuel d'essai")->firstOrFail();
        $this->aNettoyer[] = $document->id;

        $this->assertSame('rapport.pdf', $document->nom_fichier);
        $this->assertGreaterThan(0, $document->taille);
        Storage::disk(DocumentOfficiel::DISQUE)->assertExists($document->fichier_path);

        // Le chemin ne reprend pas le nom d'origine : deux « rapport.pdf »
        // déposés le même jour ne doivent pas s'écraser.
        $this->assertStringNotContainsString('rapport.pdf', $document->fichier_path);
        $composant->assertSee('Document publié');
    }

    public function test_un_gestionnaire_ne_peut_pas_publier(): void
    {
        Livewire::actingAs($this->compte('gestionnaire'))
            ->test(DocumentsOfficiels::class)
            ->set('titre', 'Tentative')
            ->set('fichier', UploadedFile::fake()->create('note.pdf', 10, 'application/pdf'))
            ->call('publier')
            ->assertForbidden();

        $this->assertSame(0, DocumentOfficiel::where('titre', 'Tentative')->count());
    }

    public function test_un_investisseur_ne_peut_pas_publier(): void
    {
        Livewire::actingAs($this->compte('investisseur'))
            ->test(DocumentsOfficiels::class)
            ->call('ouvrirFormulaire')
            ->assertForbidden();
    }

    // ---- Le fichier servi --------------------------------------------------

    public function test_un_investisseur_peut_ouvrir_un_document(): void
    {
        $document = $this->documentDeja();

        $this->actingAs($this->compte('investisseur'))
            ->get(route('documents-officiels.ouvrir', $document))
            ->assertOk()
            ->assertHeader('Content-Disposition', 'inline; filename="circulaire.pdf"');
    }

    public function test_le_telechargement_se_demande(): void
    {
        $document = $this->documentDeja();

        $this->actingAs($this->compte('gestionnaire'))
            ->get(route('documents-officiels.ouvrir', [$document, 'telecharger' => 1]))
            ->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename="circulaire.pdf"');
    }

    public function test_le_retrait_emporte_le_fichier(): void
    {
        $document = $this->documentDeja();
        $chemin = $document->fichier_path;

        Livewire::actingAs($this->compte('administrateur'))
            ->test(DocumentsOfficiels::class)
            ->call('retirer', $document->id)
            ->assertHasNoErrors();

        $this->assertSame(0, DocumentOfficiel::whereKey($document->id)->count());
        Storage::disk(DocumentOfficiel::DISQUE)->assertMissing($chemin);
    }

    // ---- Les pièces du décor ----------------------------------------------

    /** Un document déjà en place, fichier compris. */
    private function documentDeja(): DocumentOfficiel
    {
        $chemin = DocumentOfficiel::DOSSIER . '/essai-' . uniqid() . '.pdf';
        Storage::disk(DocumentOfficiel::DISQUE)->put($chemin, '%PDF-1.4 essai');

        $document = DocumentOfficiel::create([
            'titre' => "Circulaire d'essai",
            'fichier_path' => $chemin,
            'nom_fichier' => 'circulaire.pdf',
            'taille' => 14,
            'publie_par' => null,
        ]);

        $this->aNettoyer[] = $document->id;

        return $document;
    }

    private function compte(string $role): User
    {
        return User::create([
            'nom' => 'Essai',
            'prenom' => ucfirst($role),
            'email' => "documents.{$role}@local.test",
            'password' => Hash::make('quelquechose'),
            'role' => $role,
            'actif' => true,
            'doit_changer_mot_de_passe' => false,
        ]);
    }
}
