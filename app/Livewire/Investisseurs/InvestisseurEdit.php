<?php

namespace App\Livewire\Investisseurs;

use App\Models\Investisseur;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
class InvestisseurEdit extends Component
{
    use WithFileUploads;
    use \App\Support\RestreintAuPortefeuilleGestionnaire;

    public Investisseur $investisseur;

    // Identité
    #[Validate('required|in:physique,morale')]
    public string $type_personne = 'physique';

    #[Validate('required|string|max:150')]
    public string $nom = '';

    #[Validate('nullable|string|max:150')]
    public string $prenom = '';

    // Contact
    #[Validate('nullable|email|max:190')]
    public string $email = '';

    #[Validate('nullable|string|max:30')]
    public string $telephone = '';

    #[Validate('nullable|string|max:255')]
    public string $adresse = '';

    #[Validate('nullable|string|max:100')]
    public string $ville = '';

    #[Validate('nullable|string|max:100')]
    public string $pays = '';

    #[Validate('nullable|string|max:100')]
    public string $nationalite = '';

    #[Validate('nullable|date')]
    public ?string $date_naissance = null;

    #[Validate('nullable|string|max:150')]
    public string $lieu_naissance = '';

    // Pièce d'identité
    #[Validate('nullable|string|max:100')]
    public string $type_identification = '';

    #[Validate('nullable|string|max:100')]
    public string $numero_identification = '';

    #[Validate('nullable|date')]
    public ?string $date_delivrance_piece = null;

    #[Validate('nullable|string|max:150')]
    public string $lieu_delivrance_piece = '';

    #[Validate('nullable|date')]
    public ?string $date_expiration_piece = null;

    #[Validate('nullable|image|max:5120')]
    public $piece_identite_upload = null;

    // Convention d'engagement
    #[Validate('nullable|date')]
    public ?string $date_signature_convention = null;

    #[Validate('nullable|file|mimes:jpg,jpeg,png,pdf|max:10240')]
    public $convention_engagement_upload = null;

    // Personne morale
    #[Validate('nullable|string|max:255')]
    public string $raison_sociale = '';

    #[Validate('nullable|string|max:100')]
    public string $rccm = '';

    #[Validate('nullable|string|max:100')]
    public string $ninea = '';

    #[Validate('nullable|string|max:150')]
    public string $representant_legal_nom = '';

    #[Validate('nullable|string|max:30')]
    public string $representant_legal_telephone = '';

    // Bénéficiaire désigné
    #[Validate('nullable|string|max:150')]
    public string $beneficiaire_nom = '';

    #[Validate('nullable|string|max:100')]
    public string $beneficiaire_lien = '';

    #[Validate('nullable|string|max:30')]
    public string $beneficiaire_telephone = '';

    // Interne
    #[Validate('nullable|string|max:2000')]
    public string $notes_internes = '';

    // Comptes d'investissement existants (chargés dynamiquement, pas de #[Validate] fixe
    // car un investisseur peut n'avoir aucun, un seul, ou les deux comptes)
    public ?int $compteCommercialId = null;
    public bool $reinvestissementAutoCommercial = true;
    public ?int $compteWaqfId = null;
    public bool $reinvestissementAutoWaqf = true;

    protected array $champsTexte = [
        'type_personne', 'nom', 'prenom', 'email', 'telephone', 'adresse', 'ville', 'pays',
        'nationalite', 'lieu_naissance', 'type_identification', 'numero_identification', 'lieu_delivrance_piece',
        'raison_sociale', 'rccm', 'ninea', 'representant_legal_nom', 'representant_legal_telephone',
        'beneficiaire_nom', 'beneficiaire_lien', 'beneficiaire_telephone', 'notes_internes',
    ];

    public function mount(Investisseur $investisseur): void
    {
        $this->assurerAccesGestionnaire($investisseur);
        abort_if($investisseur->estDecede(), 403, 'Ce compte est gelé — l\'investisseur est déclaré décédé. Gérez la succession depuis sa fiche.');
        $this->investisseur = $investisseur;

        $valeurs = $investisseur->only($this->champsTexte);

        foreach ($this->champsTexte as $champ) {
            $this->$champ = $valeurs[$champ] ?? '';
        }

        $this->date_naissance = $investisseur->date_naissance?->toDateString();
        $this->date_delivrance_piece = $investisseur->date_delivrance_piece?->toDateString();
        $this->date_expiration_piece = $investisseur->date_expiration_piece?->toDateString();
        $this->date_signature_convention = $investisseur->date_signature_convention?->toDateString();

        $compteCommercial = $investisseur->comptes()->where('categorie', 'commercial')->first();
        if ($compteCommercial) {
            $this->compteCommercialId = $compteCommercial->id;
            $this->reinvestissementAutoCommercial = $compteCommercial->reinvestissement_auto;
        }

        $compteWaqf = $investisseur->comptes()->where('categorie', 'waqf')->first();
        if ($compteWaqf) {
            $this->compteWaqfId = $compteWaqf->id;
            $this->reinvestissementAutoWaqf = $compteWaqf->reinvestissement_auto;
        }
    }

    public function enregistrer(): void
    {
        $this->validate();

        $donnees = [
            'type_personne' => $this->type_personne,
            'nom' => $this->nom,
            'prenom' => $this->prenom ?: null,
            'email' => $this->email ?: null,
            'telephone' => \App\Support\Telephone::normaliser($this->telephone),
            'adresse' => $this->adresse ?: null,
            'ville' => $this->ville ?: null,
            'pays' => $this->pays ?: null,
            'nationalite' => $this->nationalite ?: null,
            'date_naissance' => $this->date_naissance ?: null,
            'lieu_naissance' => $this->lieu_naissance ?: null,
            'type_identification' => $this->type_identification ?: null,
            'numero_identification' => $this->numero_identification ?: null,
            'date_delivrance_piece' => $this->date_delivrance_piece ?: null,
            'lieu_delivrance_piece' => $this->lieu_delivrance_piece ?: null,
            'date_expiration_piece' => $this->date_expiration_piece ?: null,
            'date_signature_convention' => $this->date_signature_convention ?: null,
            'raison_sociale' => $this->raison_sociale ?: null,
            'rccm' => $this->rccm ?: null,
            'ninea' => $this->ninea ?: null,
            'representant_legal_nom' => $this->representant_legal_nom ?: null,
            'representant_legal_telephone' => \App\Support\Telephone::normaliser($this->representant_legal_telephone),
            'beneficiaire_nom' => $this->beneficiaire_nom ?: null,
            'beneficiaire_lien' => $this->beneficiaire_lien ?: null,
            'beneficiaire_telephone' => \App\Support\Telephone::normaliser($this->beneficiaire_telephone),
            'notes_internes' => $this->notes_internes ?: null,
        ];

        if ($this->piece_identite_upload) {
            if ($this->investisseur->piece_identite_path) {
                Storage::disk('public')->delete($this->investisseur->piece_identite_path);
            }
            $donnees['piece_identite_path'] = $this->piece_identite_upload->store('pieces-identite', 'public');
        }

        if ($this->convention_engagement_upload) {
            if ($this->investisseur->convention_engagement_path) {
                Storage::disk('public')->delete($this->investisseur->convention_engagement_path);
            }
            $donnees['convention_engagement_path'] = $this->convention_engagement_upload->store('conventions-engagement', 'public');
        }

        $donneesAvant = $this->investisseur->only(array_keys($donnees));

        $this->investisseur->update($donnees);

        \App\Models\AuditLog::enregistrer(
            action: 'modification',
            entite: 'investisseur',
            entiteId: $this->investisseur->id,
            avant: $donneesAvant,
            apres: $donnees,
        );

        if ($this->compteCommercialId) {
            \App\Models\CompteInvestissement::where('id', $this->compteCommercialId)
                ->update(['reinvestissement_auto' => $this->reinvestissementAutoCommercial]);
        }
        if ($this->compteWaqfId) {
            \App\Models\CompteInvestissement::where('id', $this->compteWaqfId)
                ->update(['reinvestissement_auto' => $this->reinvestissementAutoWaqf]);
        }

        session()->flash('succes', 'Dossier mis à jour avec succès.');

        $this->redirectRoute('investisseurs.show', $this->investisseur, navigate: true);
    }

    public function render()
    {
        return view('livewire.investisseurs.investisseur-edit');
    }
}
