<?php

namespace App\Livewire\Parametrage;

use App\Models\AuditLog;
use App\Models\ParametreDividende;
use App\Models\PolitiqueInvestissement;
use App\Support\Droits;
use App\Support\Modules;
use Livewire\Component;

/**
 * Le prix de l'action et le délai de carence.
 *
 * Ces deux valeurs décidaient de tout et ne se réglaient nulle part : elles
 * n'existaient que dans un semeur, donc il fallait passer par la base pour
 * changer le prix d'une action.
 *
 * Contrairement à une correction de barème, un changement de prix ne se propage
 * pas en arrière. Chaque achat garde le prix unitaire auquel il a été conclu —
 * c'est la colonne `prix_unitaire` de la ligne d'achat — et aucun historique
 * n'est réécrit. Seules les opérations à venir suivent le nouveau prix.
 */
class ParametresFinanciers extends Component
{
    /** @var array<string, string> categorie => prix saisi */
    public array $prix = [];

    public int $delaiEligibilite = 2;

    public function mount(): void
    {
        Droits::exiger(Modules::MODULE_VERROU, Modules::ECRITURE);

        $this->charger();
    }

    private function charger(): void
    {
        foreach (PolitiqueInvestissement::orderBy('categorie')->get() as $politique) {
            $this->prix[$politique->categorie] = (string) (int) $politique->prix_unitaire_action;
        }

        $this->delaiEligibilite = (int) (ParametreDividende::first()?->delai_eligibilite_jours ?? 2);
    }

    protected function rules(): array
    {
        $regles = [
            // Le délai compte en jours avant la fin du mois : au-delà de 28, le
            // mois le plus court n'aurait plus un seul jour éligible.
            'delaiEligibilite' => ['required', 'integer', 'min:0', 'max:28'],
        ];

        foreach (array_keys($this->prix) as $categorie) {
            // Pas de zéro : un prix nul arrête silencieusement toute souscription,
            // acheterActionsAvecSoldeDisponible() rendant la main sans rien dire.
            $regles['prix.' . $categorie] = ['required', 'numeric', 'min:1'];
        }

        return $regles;
    }

    public function enregistrer(): void
    {
        Droits::exiger(Modules::MODULE_VERROU, Modules::ECRITURE);

        $this->validate();

        $modifications = [];

        foreach (PolitiqueInvestissement::orderBy('categorie')->get() as $politique) {
            $nouveau = (float) $this->prix[$politique->categorie];
            $ancien = (float) $politique->prix_unitaire_action;

            if (abs($nouveau - $ancien) < 0.01) {
                continue;
            }

            $politique->update(['prix_unitaire_action' => $nouveau]);

            $modifications[] = sprintf('prix %s : %s → %s CFA',
                $politique->categorie,
                number_format($ancien, 0, ',', ' '),
                number_format($nouveau, 0, ',', ' '));
        }

        $parametre = ParametreDividende::firstOrCreate([], ['delai_eligibilite_jours' => $this->delaiEligibilite]);

        if ((int) $parametre->delai_eligibilite_jours !== $this->delaiEligibilite) {
            $modifications[] = sprintf('délai de carence : %d → %d jour(s)',
                $parametre->delai_eligibilite_jours, $this->delaiEligibilite);

            $parametre->update(['delai_eligibilite_jours' => $this->delaiEligibilite]);
        }

        $this->charger();

        if ($modifications === []) {
            session()->flash('info_financiers', __("Aucun changement à enregistrer."));

            return;
        }

        AuditLog::enregistrer(
            action: 'modification_parametres_financiers',
            entite: 'politique_investissement',
            apres: ['modifications' => $modifications],
        );

        session()->flash('succes_financiers', __(
            ":nombre réglage(s) modifié(s). Les opérations déjà enregistrées gardent leur prix d'origine.",
            ['nombre' => count($modifications)],
        ));
    }

    public function render()
    {
        return view('livewire.parametrage.parametres-financiers', [
            'politiques' => PolitiqueInvestissement::orderBy('categorie')->get(),
        ]);
    }
}
