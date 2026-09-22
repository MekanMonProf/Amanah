<?php

namespace App\Livewire\Exports;

use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Point d'entrée unique vers les exports globaux, jusqu'ici éparpillés en bas de
 * plusieurs écrans. La page ne fabrique aucun fichier : elle construit les liens
 * vers ExportController, qui reste seul responsable du contenu et des droits.
 */
#[Layout('layouts.app')]
class ExportIndex extends Component
{
    public string $dateDebut = '';
    public string $dateFin = '';

    public function reinitialiser(): void
    {
        $this->reset(['dateDebut', 'dateFin']);
    }

    /**
     * Bornes transmises aux exports datés. Les valeurs vides sont retirées pour
     * ne pas envoyer `date_debut=` — le contrôleur traite alors la borne comme
     * absente, mais autant garder des URL lisibles.
     */
    public function parametresDates(): array
    {
        return array_filter([
            'date_debut' => $this->dateDebut,
            'date_fin' => $this->dateFin,
        ]);
    }

    public function render()
    {
        return view('livewire.exports.export-index', [
            'periode' => $this->parametresDates(),
        ]);
    }
}
