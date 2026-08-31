<?php

namespace App\Livewire\Successions;

use App\Models\Investisseur;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
class DeclarerDeces extends Component
{
    use WithFileUploads;

    public Investisseur $investisseur;

    #[Validate('required|date|before_or_equal:today')]
    public string $dateDeces;

    #[Validate('required|file|mimes:jpg,jpeg,png,pdf|max:5120')]
    public $pieceActeDecesUpload = null;

    #[Validate('accepted')]
    public bool $confirmation = false;

    public function mount(Investisseur $investisseur): void
    {
        $this->investisseur = $investisseur;
        $this->dateDeces = now()->toDateString();
    }

    public function declarer(): void
    {
        $this->validate();

        $chemin = $this->pieceActeDecesUpload->store('actes-deces', 'public');

        $this->investisseur->update([
            'statut' => 'decede',
            'date_deces' => $this->dateDeces,
            'piece_acte_deces_path' => $chemin,
            'succession_reglee' => false,
        ]);

        \App\Models\AuditLog::enregistrer(
            action: 'declaration_deces',
            entite: 'investisseur',
            entiteId: $this->investisseur->id,
            apres: ['date_deces' => $this->dateDeces],
        );

        session()->flash('succes', 'Décès déclaré. Le compte est désormais gelé — gérez la succession pour répartir les avoirs.');

        $this->redirectRoute('successions.gerer', $this->investisseur, navigate: true);
    }

    public function render()
    {
        return view('livewire.successions.declarer-deces');
    }
}
