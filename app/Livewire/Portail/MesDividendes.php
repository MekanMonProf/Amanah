<?php

namespace App\Livewire\Portail;

use App\Models\Investisseur;
use App\Support\HistoriqueDividendes;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class MesDividendes extends Component
{
    public ?Investisseur $investisseur = null;

    public function mount(): void
    {
        // Même règle que sur les autres écrans du portail : le dossier vient du
        // compte connecté, jamais d'un paramètre d'URL.
        $this->investisseur = Auth::user()->investisseurLie;
    }

    public function render()
    {
        $historique = $this->investisseur
            ? HistoriqueDividendes::pour($this->investisseur)
            : collect();

        return view('livewire.portail.mes-dividendes', [
            'historique' => $historique,
            'cumul' => HistoriqueDividendes::cumul($historique),
            // Un dossier peut porter un compte commercial et un compte waqf, qui
            // suivent des barèmes distincts : la colonne du compte ne se justifie
            // que s'il y en a plusieurs.
            'plusieursComptes' => $this->investisseur
                ? $this->investisseur->comptes()->count() > 1
                : false,
        ]);
    }
}
