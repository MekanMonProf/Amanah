<?php

namespace App\Livewire\Dons;

use App\Models\CompteInvestissement;
use App\Models\Don;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Historique des dons d'un compte, émis comme reçus.
 *
 * Un don d'actions ne touche pas au compte financier — il déplace des titres,
 * pas de l'argent — et n'apparaissait donc nulle part : seul le nombre d'actions
 * détenues changeait, sans que rien n'en explique la raison. Cet écran est la
 * trace manquante.
 *
 * Les transferts de succession passent par la même table ; ils sont affichés
 * ici aussi, distingués par leur type d'opération.
 */
class DonsHistorique extends Component
{
    use WithPagination;

    public CompteInvestissement $compte;

    public string $recherche = '';

    /** '' = tous, 'emis' = donnés par ce compte, 'recus' = reçus par ce compte. */
    public string $filtreSens = '';

    public string $dateDebut = '';
    public string $dateFin = '';

    public string $tri = 'date_don';
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
    public function updatingFiltreSens(): void { $this->resetPage(); }
    public function updatingDateDebut(): void { $this->resetPage(); }
    public function updatingDateFin(): void { $this->resetPage(); }

    public function render()
    {
        $query = Don::with([
            'compteSource.investisseur',
            'compteDestinataire.investisseur',
        ]);

        // Le compte est soit la source, soit le destinataire — jamais les deux :
        // DonCreate refuse un don vers le compte d'origine.
        match ($this->filtreSens) {
            'emis' => $query->where('compte_source_id', $this->compte->id),
            'recus' => $query->where('compte_destinataire_id', $this->compte->id),
            default => $query->where(function ($q) {
                $q->where('compte_source_id', $this->compte->id)
                  ->orWhere('compte_destinataire_id', $this->compte->id);
            }),
        };

        if ($this->recherche) {
            $query->where('motif', 'like', "%{$this->recherche}%");
        }

        if ($this->dateDebut) {
            $query->whereDate('date_don', '>=', $this->dateDebut);
        }

        if ($this->dateFin) {
            $query->whereDate('date_don', '<=', $this->dateFin);
        }

        return view('livewire.dons.dons-historique', [
            'dons' => $query->orderBy($this->tri, $this->direction)->paginate(10),
        ]);
    }
}
