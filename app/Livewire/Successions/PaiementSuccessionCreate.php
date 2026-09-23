<?php

namespace App\Livewire\Successions;

use App\Models\CompteInvestissement;
use App\Models\Investisseur;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
class PaiementSuccessionCreate extends Component
{
    use WithFileUploads;

    public CompteInvestissement $compte;
    public Investisseur $defunt;
    public ?\App\Models\Heritier $mandataire = null;

    #[Validate('required|numeric|min:1')]
    public ?float $montant = null;

    #[Validate('required|date')]
    public string $date_paiement;

    #[Validate('required|in:Wave,Orange Money,Espèces,Virement bancaire,Chèque,Autre')]
    public string $mode_paiement = 'Wave';

    #[Validate('nullable|string|max:100')]
    public string $reference = '';

    #[Validate('required|file|mimes:jpg,jpeg,png,pdf|max:5120')]
    public $preuve_upload = null;

    /**
     * Volontairement pas de vérification "compte gelé" ici — cet écran est justement le
     * mécanisme légitime pour clôturer un compte de défunt une fois la succession réglée.
     */
    public function mount(CompteInvestissement $compte): void
    {
        $this->compte = $compte;
        $this->defunt = $compte->investisseur;
        $this->mandataire = $this->defunt->heritiers()->first();
        $this->date_paiement = now()->toDateString();
        $this->montant = $compte->solde() > 0 ? $compte->solde() : null;

        if ($this->mandataire) {
            $this->reference = "Succession {$this->defunt->identifiant_externe}";
        }
    }

    public function enregistrer(): void
    {
        $this->validate();

        if ($this->montant > $this->compte->solde()) {
            $this->addError('montant', __("Le montant dépasse le solde disponible sur ce compte (:solde).", ['solde' => \App\Support\Montant::avecDevise($this->compte->solde())]));
            return;
        }

        $cheminPreuve = $this->preuve_upload->store('preuves-paiement', 'public');

        $this->compte->ajouterEcriture(
            type: 'paiement',
            montant: -$this->montant,
            dateEcriture: $this->date_paiement,
            referenceType: 'succession_deces',
            referenceId: $this->defunt->id,
            observationCle: \App\Support\Observation::selonReference(\App\Support\Observation::VERSEMENT_SUCCESSION, \App\Support\Observation::VERSEMENT_SUCCESSION_REF, $this->reference),
            observationParametres: [
                'defunt' => trim($this->defunt->nom . ' ' . $this->defunt->prenom),
                'mandataire' => trim($this->mandataire?->nom . ' ' . $this->mandataire?->prenom),
                'mode' => $this->mode_paiement,
                'reference' => $this->reference,
            ],
            userId: Auth::id(),
            pieceJustificativePath: $cheminPreuve,
        );

        \App\Models\AuditLog::enregistrer(
            action: 'versement_succession',
            entite: 'compte_investissement',
            entiteId: $this->compte->id,
            apres: [
                'defunt' => $this->defunt->nom . ' ' . $this->defunt->prenom,
                'mandataire' => $this->mandataire?->nom . ' ' . $this->mandataire?->prenom,
                'montant' => $this->montant,
                'mode_paiement' => $this->mode_paiement,
            ],
        );

        session()->flash('succes', __("Versement de :montant enregistré.", ['montant' => \App\Support\Montant::avecDevise($this->montant)]));

        $this->redirectRoute('successions.gerer', $this->defunt, navigate: true);
    }

    public function render()
    {
        return view('livewire.successions.paiement-succession-create');
    }
}
