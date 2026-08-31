<?php

namespace App\Livewire\Radiations;

use App\Models\CompteInvestissement;
use App\Models\Radiation;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
class RadiationCreate extends Component
{
    use WithFileUploads;
    use \App\Support\RestreintAuPortefeuilleGestionnaire;

    public CompteInvestissement $compte;

    #[Validate('required|integer|min:1')]
    public ?int $nombre_actions_radiees = null;

    #[Validate('required|numeric|min:0')]
    public ?float $prix_unitaire = null;

    #[Validate('required|date')]
    public string $date_radiation;

    #[Validate('nullable|date')]
    public ?string $mois_previsionnel_paiement = null;

    #[Validate('nullable|string|max:100')]
    public string $reference_facture = '';

    #[Validate('nullable|file|mimes:jpg,jpeg,png,pdf|max:5120')]
    public $piece_justificative_upload = null;

    #[Validate('nullable|string|max:1000')]
    public string $observations = '';

    public int $actionsDetenues = 0;
    public bool $radiationAutorisee = true;

    public function mount(CompteInvestissement $compte): void
    {
        $this->assurerAccesGestionnairePourCompte($compte);
        $this->compte = $compte;
        abort_if($compte->investisseur->estDecede(), 403, 'Ce compte est gelé — l\'investisseur est déclaré décédé. Gérez la succession depuis sa fiche.');
        $this->date_radiation = now()->toDateString();
        $this->actionsDetenues = $compte->nombreActions();
        $this->prix_unitaire = (float) ($compte->politique()?->prix_unitaire_action ?? 25000);
        $this->radiationAutorisee = (bool) ($compte->politique()?->radiation_autorisee ?? true);
    }

    public function getMontantTotalProperty(): float
    {
        return ($this->nombre_actions_radiees ?? 0) * ($this->prix_unitaire ?? 0);
    }

    public function enregistrer(): void
    {
        if (! $this->radiationAutorisee) {
            $this->addError('nombre_actions_radiees', 'La radiation n\'est pas autorisée pour cette catégorie de compte.');
            return;
        }

        $this->validate();

        if ($this->nombre_actions_radiees > $this->actionsDetenues) {
            $this->addError('nombre_actions_radiees', "Impossible de radier plus d'actions que détenues ({$this->actionsDetenues}).");
            return;
        }

        $cheminPiece = null;
        if ($this->piece_justificative_upload) {
            $cheminPiece = $this->piece_justificative_upload->store('radiations', 'public');
        }

        $dernierNumero = Radiation::max('id') + 1;
        $montant = $this->nombre_actions_radiees * $this->prix_unitaire;

        $radiation = $this->compte->radiations()->create([
            'numero_radiation' => 'RAD-' . str_pad((string) $dernierNumero, 5, '0', STR_PAD_LEFT),
            'date_radiation' => $this->date_radiation,
            'nombre_actions_radiees' => $this->nombre_actions_radiees,
            'prix_unitaire_action' => $this->prix_unitaire,
            'montant_total' => $montant,
            'reference_facture' => $this->reference_facture ?: null,
            'piece_justificative_path' => $cheminPiece,
            'mois_previsionnel_paiement' => $this->mois_previsionnel_paiement ?: null,
            'observations' => $this->observations ?: null,
        ]);

        // Le capital radié devient disponible sur le compte financier — il sera ensuite
        // effectivement remis à l'investisseur via le module Paiement (traçabilité complète,
        // même logique que pour les dividendes non réinvestis).
        $this->compte->ajouterEcriture(
            type: 'radiation',
            montant: $montant,
            dateEcriture: $this->date_radiation,
            referenceType: 'radiations',
            referenceId: $radiation->id,
            observations: "Radiation {$radiation->numero_radiation} — {$this->nombre_actions_radiees} action(s), capital disponible pour versement",
            userId: Auth::id(),
        );

        session()->flash('succes', "Radiation {$radiation->numero_radiation} enregistrée : {$this->nombre_actions_radiees} action(s), {$montant} CFA crédités en attente de versement.");

        \App\Models\AuditLog::enregistrer(
            action: 'creation',
            entite: 'radiation',
            entiteId: $radiation->id,
            apres: [
                'numero_radiation' => $radiation->numero_radiation, 'compte' => $this->compte->numero_compte,
                'nombre_actions_radiees' => $this->nombre_actions_radiees, 'montant_total' => $montant,
            ],
        );

        $this->redirectRoute('investisseurs.show', $this->compte->investisseur, navigate: true);
    }

    public function render()
    {
        return view('livewire.radiations.radiation-create');
    }
}
