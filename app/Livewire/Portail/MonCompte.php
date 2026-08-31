<?php

namespace App\Livewire\Portail;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class MonCompte extends Component
{
    public ?\App\Models\Investisseur $investisseur = null;

    public function mount(): void
    {
        // Sécurité : on ne charge JAMAIS un investisseur depuis un paramètre d'URL ici —
        // uniquement celui lié au compte actuellement connecté.
        $this->investisseur = Auth::user()->investisseurLie;
    }

    public function render()
    {
        if (! $this->investisseur) {
            return view('livewire.portail.mon-compte', ['comptesEnrichis' => collect()]);
        }

        $comptes = $this->investisseur->comptes()->with(['achats', 'ecritures'])->get();

        $comptesEnrichis = $comptes->map(function ($compte) {
            return [
                'compte' => $compte,
                'nombre_actions' => $compte->nombreActions(),
                'solde' => $compte->solde(),
                'achats' => $compte->achats()->latest('date_achat')->get(),
                'dernieres_ecritures' => $compte->ecritures()->reorder('id', 'desc')->take(10)->get(),
            ];
        });

        return view('livewire.portail.mon-compte', ['comptesEnrichis' => $comptesEnrichis]);
    }
}
