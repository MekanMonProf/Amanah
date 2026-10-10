<?php

namespace App\Livewire;

use App\Models\AuditLog;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class JournalAudit extends Component
{
    use WithPagination;

    public string $recherche = '';
    public string $filtreAction = '';
    public string $filtreEntite = '';
    public string $dateDebut = '';
    public string $dateFin = '';

    public array $lignesOuvertes = [];

    protected $paginationTheme = 'tailwind';

    public function basculerDetail(int $id): void
    {
        if (in_array($id, $this->lignesOuvertes, true)) {
            $this->lignesOuvertes = array_diff($this->lignesOuvertes, [$id]);
        } else {
            $this->lignesOuvertes[] = $id;
        }
    }

    public function updatingRecherche(): void { $this->resetPage(); }
    public function updatingFiltreAction(): void { $this->resetPage(); }
    public function updatingFiltreEntite(): void { $this->resetPage(); }
    public function updatingDateDebut(): void { $this->resetPage(); }
    public function updatingDateFin(): void { $this->resetPage(); }

    public function reinitialiserFiltres(): void
    {
        $this->reset(['recherche', 'filtreAction', 'filtreEntite', 'dateDebut', 'dateFin']);
        $this->resetPage();
    }

    protected function requeteFiltree()
    {
        $query = AuditLog::with('user')->latest('created_at');

        if ($this->recherche) {
            $query->where(function ($q) {
                $q->whereHas('user', fn ($u) => $u->where('nom', 'like', "%{$this->recherche}%")->orWhere('email', 'like', "%{$this->recherche}%"))
                  ->orWhere('entite_id', $this->recherche);
            });
        }

        if ($this->filtreAction) {
            $query->where('action', $this->filtreAction);
        }

        if ($this->filtreEntite) {
            $query->where('entite', $this->filtreEntite);
        }

        if ($this->dateDebut) {
            $query->whereDate('created_at', '>=', $this->dateDebut);
        }

        if ($this->dateFin) {
            $query->whereDate('created_at', '<=', $this->dateFin);
        }

        return $query;
    }

    /**
     * Ce que le filtre courant ramène, avant d'en lire les vingt-cinq premières.
     *
     * L'écran montrait une page de résultats sans jamais dire combien il y en
     * avait, ni sur quelle étendue. Or on vient ici avec une question de
     * volume — « combien de réinitialisations de mot de passe, et depuis
     * quand » — à laquelle une page paginée ne répond pas.
     *
     * Les chiffres suivent les filtres : ils décrivent ce qu'on regarde, pas le
     * journal entier.
     *
     * @return array{actions:int, personnes:int, premiere:?string, derniere:?string}
     */
    public function getPositionProperty(): array
    {
        $bornes = $this->requeteFiltree()
            ->reorder()
            ->selectRaw('MIN(created_at) AS premiere, MAX(created_at) AS derniere')
            ->first();

        return [
            'actions' => $this->requeteFiltree()->count(),
            'personnes' => $this->requeteFiltree()->distinct()->count('user_id'),
            'premiere' => $bornes?->premiere,
            'derniere' => $bornes?->derniere,
        ];
    }

    public function render()
    {
        return view('livewire.journal-audit', [
            'entrees' => $this->requeteFiltree()->paginate(25),
            'actionsDisponibles' => AuditLog::query()->distinct()->pluck('action'),
            'entitesDisponibles' => AuditLog::query()->distinct()->pluck('entite'),
        ]);
    }
}
