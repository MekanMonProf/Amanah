<?php

namespace App\Livewire\Successions;

use App\Models\Investisseur;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Liste des successions. Jusqu'ici, une succession ne se retrouvait qu'en
 * ouvrant la fiche de l'investisseur concerné — il fallait déjà savoir qui
 * était décédé. Cet écran regroupe les dossiers et distingue ceux qui restent
 * à régler.
 */
#[Layout('layouts.app')]
class SuccessionIndex extends Component
{
    use WithPagination;

    public string $recherche = '';

    /** '' = toutes, 'en_cours' = succession non réglée, 'reglee' = succession réglée. */
    public string $filtreEtat = 'en_cours';

    protected $paginationTheme = 'tailwind';

    public function updatingRecherche(): void
    {
        $this->resetPage();
    }

    public function updatingFiltreEtat(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $requete = Investisseur::query()
            ->where('statut', 'decede')
            ->with('gestionnaire.user')
            ->withCount('heritiers')
            ->orderByDesc('date_deces');

        if ($this->recherche !== '') {
            $requete->where(function ($q) {
                $q->where('nom', 'like', "%{$this->recherche}%")
                  ->orWhere('prenom', 'like', "%{$this->recherche}%")
                  ->orWhere('identifiant_externe', 'like', "%{$this->recherche}%");
            });
        }

        if ($this->filtreEtat === 'en_cours') {
            $requete->where('succession_reglee', false);
        } elseif ($this->filtreEtat === 'reglee') {
            $requete->where('succession_reglee', true);
        }

        return view('livewire.successions.succession-index', [
            'successions' => $requete->paginate(20),
            'nombreEnCours' => Investisseur::where('statut', 'decede')->where('succession_reglee', false)->count(),
            'nombreReglees' => Investisseur::where('statut', 'decede')->where('succession_reglee', true)->count(),
        ]);
    }
}
