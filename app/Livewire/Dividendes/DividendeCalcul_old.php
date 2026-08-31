<?php

namespace App\Livewire\Dividendes;

use App\Models\BaremeDividende;
use App\Models\CompteInvestissement;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('layouts.app')]
class DividendeCalcul extends Component
{
    #[Validate('required|date')]
    public string $periode;

    #[Validate('required_if:baremeCommercialExistant,false|nullable|numeric|min:0')]
    public ?float $benefice_commercial = null;

    #[Validate('required_if:baremeWaqfExistant,false|nullable|numeric|min:0')]
    public ?float $benefice_waqf = null;

    public bool $baremeCommercialExistant = false;
    public bool $baremeWaqfExistant = false;

    public ?array $resultats = null;
    public ?array $baremesHistorique = null;

    public int $delaiEligibiliteJours = 0;
    public bool $modifierDelai = false;

    public function mount(): void
    {
        $this->periode = now()->subMonthNoOverflow()->startOfMonth()->toDateString();
        $this->chargerBaremesExistants();
        $this->chargerHistorique();
        $this->delaiEligibiliteJours = \App\Models\ParametreDividende::actuel()->delai_eligibilite_jours;
    }

    public function enregistrerDelai(): void
    {
        $this->validate(['delaiEligibiliteJours' => 'required|integer|min:0|max:30']);

        \App\Models\ParametreDividende::actuel()->update(['delai_eligibilite_jours' => $this->delaiEligibiliteJours]);
        $this->modifierDelai = false;

        session()->flash('succes_parametre', 'Règle d\'éligibilité mise à jour.');
    }

    public function updatedPeriode(): void
    {
        $this->chargerBaremesExistants();
        $this->resultats = null;
    }

    /**
     * Si un barème existe déjà pour cette période, on le pré-remplit et on verrouille
     * le champ — un taux déjà appliqué à des comptes ne doit plus jamais être modifiable ici.
     */
    protected function chargerBaremesExistants(): void
    {
        $periodeDebut = Carbon::parse($this->periode)->startOfMonth()->toDateString();

        $bCommercial = BaremeDividende::where('periode', $periodeDebut)->where('categorie', 'commercial')->first();
        $bWaqf = BaremeDividende::where('periode', $periodeDebut)->where('categorie', 'waqf')->first();

        $this->baremeCommercialExistant = (bool) $bCommercial;
        $this->baremeWaqfExistant = (bool) $bWaqf;

        $this->benefice_commercial = $bCommercial ? (float) $bCommercial->benefice_par_action : null;
        $this->benefice_waqf = $bWaqf ? (float) $bWaqf->benefice_par_action : null;
    }

    protected function chargerHistorique(): void
    {
        $this->baremesHistorique = BaremeDividende::orderByDesc('periode')->get()
            ->groupBy(fn ($b) => $b->periode->format('Y-m'))
            ->map(fn ($groupe) => $groupe->keyBy('categorie'))
            ->toArray();
    }

    /**
     * 1) Fixe (ou confirme) le barème de la période sélectionnée.
     * 2) Rejoue ensuite TOUT l'historique connu des barèmes, pour tous les comptes éligibles :
     *    tout compte n'ayant pas encore reçu le dividende d'une période passée (ex: ouvert
     *    après coup) le reçoit automatiquement, avec le taux qui était en vigueur à l'époque.
     */
    public function calculerEtDistribuer(): void
    {
        $this->validate();

        $periodeDebut = Carbon::parse($this->periode)->startOfMonth();

        // Fixation du barème de la période choisie (une seule fois, jamais modifiable ensuite)
        if (! $this->baremeCommercialExistant && $this->benefice_commercial !== null) {
            BaremeDividende::create([
                'periode' => $periodeDebut->toDateString(),
                'categorie' => 'commercial',
                'benefice_par_action' => $this->benefice_commercial,
                'fixe_par' => Auth::id(),
            ]);
        }
        if (! $this->baremeWaqfExistant && $this->benefice_waqf !== null) {
            BaremeDividende::create([
                'periode' => $periodeDebut->toDateString(),
                'categorie' => 'waqf',
                'benefice_par_action' => $this->benefice_waqf,
                'fixe_par' => Auth::id(),
            ]);
        }

        // Rattrapage : on rejoue TOUS les barèmes connus, du plus ancien au plus récent,
        // pour que l'ordre chronologique du solde reste cohérent (voir CompteInvestissement::solde()).
        $tousLesBaremes = BaremeDividende::orderBy('periode')->get();
        $delaiJours = \App\Models\ParametreDividende::actuel()->delai_eligibilite_jours;

        $resultats = [
            'commercial' => ['nb_comptes' => 0, 'total_distribue' => 0, 'deja_traites' => 0],
            'waqf' => ['nb_comptes' => 0, 'total_distribue' => 0, 'deja_traites' => 0],
        ];

        foreach ($tousLesBaremes as $bareme) {
            $periodeBareme = $bareme->periode;
            $categorie = $bareme->categorie;
            $beneficeParAction = (float) $bareme->benefice_par_action;

            $comptes = CompteInvestissement::where('categorie', $categorie)
                ->where('statut', 'actif')
                ->get();

            foreach ($comptes as $compte) {
                if (! $compte->politique()?->eligible_dividendes) {
                    continue;
                }

                // Éligibilité basée sur la date RÉELLE du premier achat (pas la date d'ouverture
                // du compte en base), avec un délai de carence configurable en fin de mois.
                $dateLimite = $periodeBareme->copy()->endOfMonth()->subDays($delaiJours);

                $possedaitDejaDesActions = $compte->achats()
                    ->whereDate('date_achat', '<=', $dateLimite)
                    ->exists();

                if (! $possedaitDejaDesActions) {
                    continue;
                }

                $dejaCalcule = $compte->dividendes()->where('periode', $periodeBareme->toDateString())->exists();
                if ($dejaCalcule) {
                    $resultats[$categorie]['deja_traites']++;
                    continue;
                }

                $nombreActions = $compte->nombreActions();
                if ($nombreActions <= 0) {
                    continue;
                }

                $montant = round($nombreActions * $beneficeParAction, 2);

                DB::transaction(function () use ($compte, $periodeBareme, $nombreActions, $beneficeParAction, $montant) {
                    $dividende = $compte->dividendes()->create([
                        'periode' => $periodeBareme->toDateString(),
                        'nombre_actions' => $nombreActions,
                        'benefice_par_action' => $beneficeParAction,
                        'montant_calcule' => $montant,
                        'statut' => 'credite',
                    ]);

                    $compte->ajouterEcriture(
                        type: 'dividende',
                        montant: $montant,
                        dateEcriture: $periodeBareme->toDateString(),
                        referenceType: 'dividendes',
                        referenceId: $dividende->id,
                        observations: 'Dividende ' . $periodeBareme->translatedFormat('F Y'),
                        userId: Auth::id(),
                    );

                    $compte->tenterReinvestissementAutomatique(Auth::id());
                });

                $resultats[$categorie]['nb_comptes']++;
                $resultats[$categorie]['total_distribue'] += $montant;
            }
        }

        $this->resultats = $resultats;
        $this->chargerBaremesExistants();
        $this->chargerHistorique();
    }

    public function render()
    {
        return view('livewire.dividendes.dividende-calcul');
    }
}
