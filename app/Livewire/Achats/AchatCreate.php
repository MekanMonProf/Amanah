<?php

namespace App\Livewire\Achats;

use App\Models\Investisseur;
use App\Models\PolitiqueInvestissement;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
class AchatCreate extends Component
{
    use WithFileUploads;
    use \App\Support\RestreintAuPortefeuilleGestionnaire;

    public Investisseur $investisseur;

    #[Validate('required|in:commercial,waqf')]
    public string $categorie = 'commercial';

    #[Validate('required|date')]
    public string $date_achat;

    #[Validate('required|in:initial,rajout,complement,benefice')]
    public string $type_achat = 'initial';

    #[Validate('required|integer|min:1')]
    public ?int $nombre_actions = null;

    #[Validate('required|numeric|min:0')]
    public ?float $prix_unitaire = null;

    #[Validate('required|in:Wave,Orange Money,Espèces,Virement bancaire,Chèque,Autre')]
    public string $mode_paiement = 'Wave';

    #[Validate('nullable|string|max:100')]
    public string $reference_facture = '';

    #[Validate('nullable|file|mimes:jpg,jpeg,png,pdf|max:5120')]
    public $facture_upload = null;

    #[Validate('nullable|string|max:1000')]
    public string $observations = '';

    public ?float $montantCalcule = null;

    public function mount(Investisseur $investisseur): void
    {
        $this->assurerAccesGestionnaire($investisseur);
        $this->investisseur = $investisseur;
        abort_if($investisseur->estDecede(), 403, 'Ce compte est gelé — l\'investisseur est déclaré décédé. Gérez la succession depuis sa fiche.');
        $this->date_achat = now()->toDateString();
        $this->appliquerPrixParDefaut();
    }

    public function updatedCategorie(): void
    {
        $this->appliquerPrixParDefaut();
    }

    protected function appliquerPrixParDefaut(): void
    {
        $politique = PolitiqueInvestissement::where('categorie', $this->categorie)->first();
        $this->prix_unitaire = $politique?->prix_unitaire_action ?? 25000;
    }

    public function updated($property): void
    {
        if (in_array($property, ['nombre_actions', 'prix_unitaire'])) {
            $this->montantCalcule = ($this->nombre_actions ?? 0) * ($this->prix_unitaire ?? 0);
        }
    }

    public function enregistrer(): void
    {
        $this->validate();

        // Le compte est créé automatiquement s'il n'existe pas encore (règle métier section 7)
        $compte = $this->investisseur->compteOuCree($this->categorie);

        // Si cet achat est antérieur à la date d'ouverture enregistrée, on aligne l'ouverture
        // du compte sur cette date réelle — important pour l'éligibilité aux dividendes passés.
        if ($this->date_achat < $compte->date_ouverture->toDateString()) {
            $compte->update(['date_ouverture' => $this->date_achat]);
        }

        $dernierNumero = \App\Models\AchatAction::max('id') + 1;

        $cheminFacture = null;
        if ($this->facture_upload) {
            $cheminFacture = $this->facture_upload->store('factures-achats', 'public');
        }

        $achat = $compte->achats()->create([
            'numero_achat' => 'ACH-' . str_pad((string) $dernierNumero, 5, '0', STR_PAD_LEFT),
            'date_achat' => $this->date_achat,
            'type_achat' => $this->type_achat,
            'nombre_actions' => $this->nombre_actions,
            'prix_unitaire' => $this->prix_unitaire,
            'montant' => $this->nombre_actions * $this->prix_unitaire,
            'mode_paiement' => $this->mode_paiement,
            'reference_facture' => $this->reference_facture ?: null,
            'photo_facture_path' => $cheminFacture,
            'observations' => $this->observations ?: null,
            'saisi_par' => Auth::id(),
        ]);

        session()->flash('succes', "Achat {$achat->numero_achat} enregistré : {$this->nombre_actions} action(s) pour le compte {$compte->numero_compte}.");

        \App\Models\AuditLog::enregistrer(
            action: 'creation',
            entite: 'achat',
            entiteId: $achat->id,
            apres: [
                'numero_achat' => $achat->numero_achat, 'compte' => $compte->numero_compte,
                'nombre_actions' => $achat->nombre_actions, 'montant' => (float) $achat->montant,
            ],
        );

        $this->redirectRoute('investisseurs.show', $this->investisseur, navigate: true);
    }

    public function render()
    {
        return view('livewire.achats.achat-create');
    }
}
