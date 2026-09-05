<?php

namespace App\Livewire\Achats;

use App\Models\CompteInvestissement;
use Livewire\Component;
use Livewire\WithPagination;

class AchatsHistorique extends Component
{
    use WithPagination;

    public CompteInvestissement $compte;

    public string $recherche = '';
    public string $filtreType = '';
    public string $dateDebut = '';
    public string $dateFin = '';

    public string $tri = 'date_achat';
    public string $direction = 'desc';

    protected $paginationTheme = 'tailwind';

    public function mount(CompteInvestissement $compte): void
    {
        $this->compte = $compte;
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
    public function updatingFiltreType(): void { $this->resetPage(); }
    public function updatingDateDebut(): void { $this->resetPage(); }
    public function updatingDateFin(): void { $this->resetPage(); }

    public function render()
    {
        // offertPar est chargé d'avance : sur le compte du Waqf caritatif, chaque ligne peut
        // être une offrande et afficherait sinon une requête par achat.
        $query = $this->compte->achats()->with('offertPar');

        if ($this->recherche) {
            $query->where(function ($q) {
                $q->where('numero_achat', 'like', "%{$this->recherche}%")
                  ->orWhere('reference_facture', 'like', "%{$this->recherche}%")
                  ->orWhere('mode_paiement', 'like', "%{$this->recherche}%")
                  ->orWhere('offrande_pour', 'like', "%{$this->recherche}%");
            });
        }

        if ($this->filtreType) {
            $query->where('type_achat', $this->filtreType);
        }

        if ($this->dateDebut) {
            $query->whereDate('date_achat', '>=', $this->dateDebut);
        }

        if ($this->dateFin) {
            $query->whereDate('date_achat', '<=', $this->dateFin);
        }

        $achats = $query->orderBy($this->tri, $this->direction)->paginate(10);

        return view('livewire.achats.achats-historique', ['achats' => $achats]);
    }
}
