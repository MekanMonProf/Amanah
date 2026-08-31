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

    public function mount(CompteInvestissement $compte): void
    {
        $this->compte = $compte;
        $this->date_ecriture = now()->toDateString();
    }

    public function enregistrer(): void
    {
        $this->validate();

        $this->compte->ajouterEcriture(
            type: 'ajustement',
            montant: $this->montant,
            dateEcriture: $this->date_ecriture,
            observations: $this->observations,
            userId: Auth::id(),
        );

        session()->flash('succes', 'Ajustement enregistré. Le solde du compte a été corrigé.');

        \App\Models\AuditLog::enregistrer(
            action: 'ajustement',
            entite: 'compte_investissement',
            entiteId: $this->compte->id,
            apres: [
                'compte' => $this->compte->numero_compte, 'montant' => $this->montant,
                'motif' => $this->observations,
            ],
        );

        $this->redirectRoute('investisseurs.show', $this->compte->investisseur, navigate: true);
    }

    public function render()
    {
        return view('livewire.comptes.ajustement-create');
    }
}
