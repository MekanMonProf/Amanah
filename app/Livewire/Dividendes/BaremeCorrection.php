<?php

namespace App\Livewire\Dividendes;

use App\Models\BaremeDividende;
use App\Models\Dividende;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('layouts.app')]
class BaremeCorrection extends Component
{
    public BaremeDividende $bareme;

    public float $ancienTaux;

    #[Validate('required|numeric|min:0')]
    public ?float $nouveauTaux = null;

    #[Validate('required|string|min:15|max:1000')]
    public string $motif = '';

    public ?array $resultat = null;

    public function mount(BaremeDividende $bareme): void
    {
        $this->bareme = $bareme;
        $this->ancienTaux = (float) $bareme->benefice_par_action;
    }

    public function nombreComptesConcernes(): int
    {
        return Dividende::where('periode', $this->bareme->periode)
            ->whereHas('compte', fn ($q) => $q->where('categorie', $this->bareme->categorie))
            ->count();
    }

    /**
     * Corrige le taux du barème et répercute l'écart sur chaque compte déjà crédité,
     * via une écriture d'ajustement — jamais en modifiant les écritures déjà enregistrées.
     */
    public function corriger(): void
    {
        $this->validate();

        if ($this->nouveauTaux == $this->ancienTaux) {
            $this->addError('nouveauTaux', 'Le nouveau taux est identique à l\'ancien — rien à corriger.');
            return;
        }

        $dividendesConcernes = Dividende::where('periode', $this->bareme->periode)
            ->whereHas('compte', fn ($q) => $q->where('categorie', $this->bareme->categorie))
            ->with('compte')
            ->get();

        $nbComptesAjustes = 0;
        $totalAjuste = 0;

        DB::transaction(function () use ($dividendesConcernes, &$nbComptesAjustes, &$totalAjuste) {
            // 1) Le barème lui-même est mis à jour (la valeur affichée pour cette période)
            $this->bareme->update(['benefice_par_action' => $this->nouveauTaux]);

            // 2) Chaque compte déjà crédité à l'ancien taux reçoit un ajustement correctif
            foreach ($dividendesConcernes as $dividende) {
                $delta = round(($this->nouveauTaux - $this->ancienTaux) * $dividende->nombre_actions, 2);

                if ($delta == 0) {
                    continue;
                }

                $compte = $dividende->compte;

                $compte->ajouterEcriture(
                    type: 'ajustement',
                    montant: $delta,
                    dateEcriture: now()->toDateString(),
                    referenceType: 'dividendes',
                    referenceId: $dividende->id,
                    observations: sprintf(
                        'Correction barème %s (%s) : %s → %s CFA/action. Motif : %s',
                        $this->bareme->periode->translatedFormat('F Y'),
                        ucfirst($this->bareme->categorie),
                        \App\Support\Montant::format($this->ancienTaux),
                        \App\Support\Montant::format($this->nouveauTaux),
                        $this->motif
                    ),
                    userId: Auth::id(),
                );

                // On met à jour le dividende pour que les futurs rapports reflètent le taux corrigé
                $dividende->update([
                    'benefice_par_action' => $this->nouveauTaux,
                    'montant_calcule' => round($dividende->nombre_actions * $this->nouveauTaux, 2),
                ]);

                // Si la correction fait remonter le solde, le réinvestissement automatique peut se déclencher
                $compte->tenterReinvestissementAutomatique(Auth::id());

                $nbComptesAjustes++;
                $totalAjuste += $delta;
            }
        });

        \App\Models\AuditLog::enregistrer(
            action: 'correction_bareme',
            entite: 'bareme_dividende',
            entiteId: $this->bareme->id,
            avant: ['taux' => (float) $this->ancienTaux],
            apres: [
                'taux' => $this->nouveauTaux, 'periode' => $this->bareme->periode->format('Y-m'),
                'categorie' => $this->bareme->categorie, 'motif' => $this->motif,
                'comptes_ajustes' => $nbComptesAjustes,
            ],
        );

        $this->resultat = [
            'nb_comptes' => $nbComptesAjustes,
            'total_ajuste' => $totalAjuste,
        ];

        $this->ancienTaux = $this->nouveauTaux;
    }

    public function render()
    {
        return view('livewire.dividendes.bareme-correction', [
            'nbComptesConcernes' => $this->nombreComptesConcernes(),
        ]);
    }
}
