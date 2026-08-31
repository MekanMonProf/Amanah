<?php

namespace App\Livewire\Comptes;

use App\Models\CompteInvestissement;
use Livewire\Component;
use Livewire\WithPagination;

class EcrituresHistorique extends Component
{
    use WithPagination;

    public CompteInvestissement $compte;

    public string $recherche = '';
    public string $filtreType = '';
    public string $dateDebut = '';
    public string $dateFin = '';

    // Tri par ordre d'insertion par défaut (id) : c'est l'ordre chronologique réel du solde,
    // voir la note dans CompteInvestissement::solde(). L'utilisateur peut re-trier par date_ecriture s'il le souhaite.
    public string $tri = 'id';
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
        // reorder() efface le tri par défaut de la relation (orderBy id) pour éviter
        // un conflit de clauses ORDER BY — on applique notre propre tri ci-dessous.
        $query = $this->compte->ecritures()->getQuery()->reorder();

        if ($this->recherche) {
            $query->where('observations', 'like', "%{$this->recherche}%");
        }

        if ($this->filtreType) {
            $query->where('type_ecriture', $this->filtreType);
        }

        if ($this->dateDebut) {
            $query->whereDate('date_ecriture', '>=', $this->dateDebut);
        }

        if ($this->dateFin) {
            $query->whereDate('date_ecriture', '<=', $this->dateFin);
        }

        $ecritures = (clone $query)->orderBy($this->tri, $this->direction)->paginate(10);

        // Rang réel (ordre d'insertion) de CHAQUE écriture du compte, indépendant du tri affiché
        // et de la pagination — c'est ce qui permet d'afficher une colonne "#" toujours fiable.
        $rangs = $this->compte->ecritures()->getQuery()->reorder('id', 'asc')->pluck('id')
            ->flip()
            ->map(fn ($index) => $index + 1);

        return view('livewire.comptes.ecritures-historique', ['ecritures' => $ecritures, 'rangs' => $rangs]);
    }
}
