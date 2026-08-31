<?php

namespace App\Livewire\Investisseurs;

use App\Models\Gestionnaire;
use App\Models\Investisseur;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class InvestisseurIndex extends Component
{
    use WithPagination;

    public string $recherche = '';
    public ?int $filtreGestionnaireId = null;
    public string $filtreStatut = '';
    public bool $afficherFormulaire = false;

    public string $tri = 'nom';
    public string $direction = 'asc';

    protected $paginationTheme = 'tailwind';

    // --- Champs du formulaire de création ---
    #[Validate('required|string|max:150')]
    public string $nom = '';

    #[Validate('nullable|string|max:150')]
    public string $prenom = '';

    public string $identifiant_externe = '';

    #[Validate('nullable|string|max:30')]
    public string $telephone = '';

    #[Validate('nullable|email|max:190')]
    public string $email = '';

    #[Validate('nullable|string|max:100')]
    public string $pays = '';

    #[Validate('required_if:role_courant,direction,administrateur|nullable|exists:gestionnaires,id')]
    public ?int $gestionnaire_id = null;

    public function mount(): void
    {
        // Un gestionnaire ne voit et ne crée que pour lui-même par défaut
        if (Auth::user()->role === 'gestionnaire') {
            $this->filtreGestionnaireId = Auth::user()->gestionnaire?->id;
            $this->gestionnaire_id = $this->filtreGestionnaireId;
        } elseif (request()->has('gestionnaire')) {
            $this->filtreGestionnaireId = (int) request()->query('gestionnaire');
        }
    }

    public function trierPar(string $colonne): void
    {
        if ($this->tri === $colonne) {
            $this->direction = $this->direction === 'asc' ? 'desc' : 'asc';
        } else {
            $this->tri = $colonne;
            $this->direction = 'asc';
        }
        $this->resetPage();
    }

    public function updatingRecherche(): void { $this->resetPage(); }
    public function updatingFiltreGestionnaireId(): void { $this->resetPage(); }
    public function updatingFiltreStatut(): void { $this->resetPage(); }

    public function reinitialiserFiltres(): void
    {
        $this->reset(['recherche', 'filtreStatut']);
        if (Auth::user()->role !== 'gestionnaire') {
            $this->reset('filtreGestionnaireId');
        }
        $this->resetPage();
    }

    /**
     * Prochain identifiant disponible, affiché en aperçu dans le formulaire.
     */
    public function getProchainIdentifiantProperty(): string
    {
        return $this->genererProchainIdentifiant();
    }

    protected function genererProchainIdentifiant(): string
    {
        $dernier = Investisseur::where('identifiant_externe', 'like', 'A%')
            ->orderByRaw('CAST(SUBSTRING(identifiant_externe, 2) AS UNSIGNED) DESC')
            ->value('identifiant_externe');

        $prochainNumero = $dernier ? ((int) substr($dernier, 1)) + 1 : 1;

        return 'A' . str_pad((string) $prochainNumero, 4, '0', STR_PAD_LEFT);
    }

    public function creer(): void
    {
        $this->validate();

        do {
            $identifiant = $this->genererProchainIdentifiant();
        } while (Investisseur::where('identifiant_externe', $identifiant)->exists());

        $investisseur = Investisseur::create([
            'identifiant_externe' => $identifiant,
            'nom' => $this->nom,
            'prenom' => $this->prenom,
            'telephone' => $this->telephone,
            'email' => $this->email ?: null,
            'pays' => $this->pays,
            'gestionnaire_id' => $this->gestionnaire_id,
            'statut' => 'actif',
        ]);

        $this->reset(['nom', 'prenom', 'telephone', 'email', 'pays', 'afficherFormulaire']);
        \App\Models\AuditLog::enregistrer(
            action: 'creation',
            entite: 'investisseur',
            entiteId: $investisseur->id,
            apres: ['identifiant_externe' => $investisseur->identifiant_externe, 'nom' => $investisseur->nom, 'prenom' => $investisseur->prenom],
        );

        $this->dispatch('investisseur-cree', id: $investisseur->id);
    }

    /**
     * Construit la requête filtrée — factorisé pour être identique entre l'affichage
     * et l'export, afin que l'export corresponde toujours exactement à ce qui est affiché.
     */
    protected function requeteFiltree()
    {
        $query = Investisseur::query()->with('gestionnaire.user');

        if ($this->recherche) {
            $query->where(function ($q) {
                $q->where('nom', 'like', "%{$this->recherche}%")
                  ->orWhere('prenom', 'like', "%{$this->recherche}%")
                  ->orWhere('identifiant_externe', 'like', "%{$this->recherche}%");
            });
        }

        if ($this->filtreGestionnaireId) {
            $query->where('gestionnaire_id', $this->filtreGestionnaireId);
        }

        if ($this->filtreStatut) {
            $query->where('statut', $this->filtreStatut);
        }

        return $query;
    }

    public function render()
    {
        return view('livewire.investisseurs.investisseur-index', [
            'investisseurs' => $this->requeteFiltree()->orderBy($this->tri, $this->direction)->paginate(20),
            'gestionnaires' => Auth::user()->role !== 'gestionnaire'
                ? Gestionnaire::with('user')->where('actif', true)->get()
                : collect(),
        ]);
    }
}
