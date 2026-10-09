<?php

namespace App\Livewire\Portail;

use App\Models\Investisseur;
use App\Support\DocumentsDuDossier;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class MesDocuments extends Component
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
        $documents = $this->investisseur
            ? DocumentsDuDossier::pour($this->investisseur)
            : collect();

        return view('livewire.portail.mes-documents', [
            'documents' => $documents,
            'total' => $documents->count(),
        ]);
    }
}
