<?php

namespace App\Livewire\Comptes;

use App\Models\CompteInvestissement;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
class ComplementFinancierCreate extends Component
{
    use WithFileUploads;
    use \App\Support\RestreintAuPortefeuilleGestionnaire;

    public CompteInvestissement $compte;

    #[Validate('required|numeric|min:1')]
    public ?float $montant = null;

    #[Validate('required|date')]
    public string $date_versement;

    #[Validate('required|in:Wave,Orange Money,Espèces,Virement bancaire,Chèque,Autre')]
    public string $mode_paiement = 'Wave';

    #[Validate('nullable|string|max:100')]
    public string $reference = '';

    #[Validate('nullable|file|mimes:jpg,jpeg,png,pdf|max:5120')]
    public $piece_justificative_upload = null;

    public ?float $prixAction = null;
    public ?int $resultatNbAchats = null;
    public ?float $resultatMontantAvant = null;
    public ?float $resultatMontantApres = null;

    public function mount(CompteInvestissement $compte): void
    {
        $this->assurerAccesGestionnairePourCompte($compte);
        $this->compte = $compte;
        abort_if($compte->investisseur->estDecede(), 403, __("Ce compte est gelé — l'investisseur est déclaré décédé. Gérez la succession depuis sa fiche."));
        $this->date_versement = now()->toDateString();
        $this->prixAction = (float) ($compte->politique()?->prix_unitaire_action ?? 25000);
    }

    public function enregistrer(): void
    {
        $this->validate();

        $soldeAvant = $this->compte->solde();

        $cheminPiece = null;
        if ($this->piece_justificative_upload) {
            $cheminPiece = $this->piece_justificative_upload->store('complements-financiers', 'public');
        }

        $this->compte->ajouterEcriture(
            type: 'versement_complementaire',
            montant: $this->montant,
            dateEcriture: $this->date_versement,
            observationCle: \App\Support\Observation::selonReference(\App\Support\Observation::COMPLEMENT, \App\Support\Observation::COMPLEMENT_REF, $this->reference),
            observationParametres: ['mode' => $this->mode_paiement, 'reference' => $this->reference],
            userId: Auth::id(),
            pieceJustificativePath: $cheminPiece,
        );

        // Achat immédiat de toutes les actions que le nouveau solde permet (section 11)
        $nbAchats = $this->compte->acheterActionsAvecSoldeDisponible(
            typeAchat: 'complement',
            observationCle: \App\Support\Observation::ACHAT_COMPLEMENT,
            userId: Auth::id(),
        );

        $this->resultatNbAchats = $nbAchats;

        \App\Models\AuditLog::enregistrer(
            action: 'complement_financier',
            entite: 'compte_investissement',
            entiteId: $this->compte->id,
            apres: [
                'compte' => $this->compte->numero_compte, 'montant_verse' => $this->montant,
                'mode_paiement' => $this->mode_paiement, 'actions_achetees' => $nbAchats,
            ],
        );
        $this->resultatMontantAvant = $soldeAvant;
        $this->resultatMontantApres = $this->compte->solde();

        $this->reset(['montant', 'reference', 'piece_justificative_upload']);
    }

    public function render()
    {
        return view('livewire.comptes.complement-financier-create');
    }
}
