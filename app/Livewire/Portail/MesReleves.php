<?php

namespace App\Livewire\Portail;

use App\Models\Investisseur;
use App\Support\PeriodesReleve;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class MesReleves extends Component
{
    public ?Investisseur $investisseur = null;

    public function mount(): void
    {
        // Même règle que sur « Mon compte » : le dossier vient du compte connecté,
        // jamais d'un paramètre d'URL. Un identifiant dans l'adresse se devine.
        $this->investisseur = Auth::user()->investisseurLie;
    }

    public function render()
    {
        $periodes = $this->investisseur
            ? PeriodesReleve::pour($this->investisseur)
            : collect();

        return view('livewire.portail.mes-releves', [
            'parAnnee' => $periodes->groupBy('annee'),
            'total' => $periodes->count(),
        ]);
    }
}
