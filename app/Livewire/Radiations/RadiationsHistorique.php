<?php

namespace App\Livewire\Radiations;

use App\Models\CompteInvestissement;
use App\Models\EcritureCompteFinancier;
use Livewire\Component;
use Livewire\WithPagination;

class RadiationsHistorique extends Component
{
    use WithPagination;

    public CompteInvestissement $compte;

    public string $recherche = '';
    public string $dateDebut = '';
    public string $dateFin = '';

    public string $tri = 'date_radiation';
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
    public function updatingDateDebut(): void { $this->resetPage(); }
    public function updatingDateFin(): void { $this->resetPage(); }

    public function render()
    {
        $query = $this->compte->radiations()->getQuery()->reorder();

        if ($this->recherche) {
            $query->where(function ($q) {
                $q->where('numero_radiation', 'like', "%{$this->recherche}%")
                  ->orWhere('reference_facture', 'like', "%{$this->recherche}%");
            });
        }

        if ($this->dateDebut) {
            $query->whereDate('date_radiation', '>=', $this->dateDebut);
        }

        if ($this->dateFin) {
            $query->whereDate('date_radiation', '<=', $this->dateFin);
        }

        $radiations = $query->orderBy($this->tri, $this->direction)->paginate(10);

        // Montant déjà versé par radiation, calculé depuis les écritures de paiement qui y
        // font référence (reference_type='radiations') — jamais stocké, toujours recalculé.
        $idsRadiations = $radiations->pluck('id');
        $montantsVerses = EcritureCompteFinancier::where('reference_type', 'radiations')
            ->whereIn('reference_id', $idsRadiations)
            ->where('type_ecriture', 'paiement')
            ->selectRaw('reference_id, SUM(-montant) as total_verse')
            ->groupBy('reference_id')
            ->pluck('total_verse', 'reference_id');

        return view('livewire.radiations.radiations-historique', [
            'radiations' => $radiations,
            'montantsVerses' => $montantsVerses,
        ]);
    }
}
