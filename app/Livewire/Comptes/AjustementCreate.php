<?php

namespace App\Livewire\Comptes;

use App\Models\CompteInvestissement;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('layouts.app')]
class AjustementCreate extends Component
{
    public CompteInvestissement $compte;

    #[Validate('required|numeric')]
    public ?float $montant = null;

    #[Validate('required|date')]
    public string $date_ecriture;

    #[Validate('required|string|min:15|max:1000')]
    public string $observations = '';

    /**
     * L'écriture qui vient d'être créée, tant que l'écran propose son reçu.
     *
     * L'écran ne repart plus tout seul vers la fiche : le moment où l'on
     * vient d'enregistrer est celui où l'on tient encore l'investisseur, et
     * donc celui où la question de lui envoyer son reçu se pose.
     */
    public ?int $ecritureDuRecu = null;

    public function mount(CompteInvestissement $compte): void
    {
        $this->compte = $compte;
        $this->date_ecriture = now()->toDateString();
    }

    public function enregistrer(): void
    {
        $this->validate();

        $ecriture = $this->compte->ajouterEcriture(
            type: 'ajustement',
            montant: $this->montant,
            dateEcriture: $this->date_ecriture,
            observations: $this->observations,
            userId: Auth::id(),
        );

        session()->flash('succes', __("Ajustement enregistré. Le solde du compte a été corrigé."));

        \App\Models\AuditLog::enregistrer(
            action: 'ajustement',
            entite: 'compte_investissement',
            entiteId: $this->compte->id,
            apres: [
                'compte' => $this->compte->numero_compte, 'montant' => $this->montant,
                'motif' => $this->observations,
            ],
        );

        $this->ecritureDuRecu = $ecriture->id;
    }

    public function render()
    {
        return view('livewire.comptes.ajustement-create');
    }
}
