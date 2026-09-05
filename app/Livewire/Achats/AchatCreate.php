<?php

namespace App\Livewire\Achats;

use App\Models\Investisseur;
use App\Models\PolitiqueInvestissement;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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

    /**
     * Achat Waqf offert à la mémoire d'un défunt : les actions vont au compte institutionnel
     * « Waqf Dolel Xamxam », pas au compte de l'investisseur qui paie.
     */
    public bool $enMemoire = false;

    /** 'interne' = défunt déjà enregistré comme investisseur, 'externe' = personne extérieure. */
    public string $defuntSource = 'interne';

    public ?int $defuntInvestisseurId = null;

    public string $defuntNom = '';

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

        // L'offrande à la mémoire d'un défunt n'a de sens qu'en Waqf : le capital y est
        // inaliénable et sans versement de dividendes, donc réellement donné.
        if ($this->categorie !== 'waqf') {
            $this->enMemoire = false;
        }
    }

    /**
     * Les défunts sélectionnables : investisseurs déclarés décédés. Le donateur lui-même
     * ne peut pas y figurer (mount() interdit déjà la page à un investisseur décédé).
     */
    public function getDefuntsDisponiblesProperty()
    {
        return Investisseur::where('statut', 'decede')
            ->orderBy('nom')->orderBy('prenom')
            ->get(['id', 'nom', 'prenom', 'identifiant_externe', 'date_deces']);
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

    /**
     * Revalide côté serveur tout ce qui touche à l'offrande : les propriétés Livewire sont
     * modifiables par le client, on ne se fie donc pas à l'affichage conditionnel de la vue.
     * Retourne le défunt quand c'est un investisseur de la plateforme, null s'il est extérieur.
     */
    protected function validerDefunt(): ?Investisseur
    {
        if ($this->categorie !== 'waqf') {
            throw ValidationException::withMessages([
                'enMemoire' => 'Une offrande à la mémoire d\'un défunt n\'est possible qu\'en catégorie Waqf.',
            ]);
        }

        $this->validate([
            'defuntSource' => ['required', 'in:interne,externe'],
        ]);

        if ($this->defuntSource === 'externe') {
            $this->validate([
                'defuntNom' => ['required', 'string', 'min:3', 'max:150'],
            ], attributes: ['defuntNom' => 'nom du défunt']);

            return null;
        }

        $this->validate([
            'defuntInvestisseurId' => [
                'required',
                Rule::exists('investisseurs', 'id')->where('statut', 'decede'),
            ],
        ], messages: [
            'defuntInvestisseurId.required' => 'Sélectionnez le défunt honoré.',
            'defuntInvestisseurId.exists' => 'Cet investisseur n\'est pas déclaré décédé sur la plateforme.',
        ]);

        return Investisseur::findOrFail($this->defuntInvestisseurId);
    }

    protected function nomDuDefunt(?Investisseur $defunt): string
    {
        return $defunt
            ? trim($defunt->nom . ' ' . $defunt->prenom)
            : trim($this->defuntNom);
    }

    public function enregistrer(): void
    {
        $this->validate();

        $defunt = $this->enMemoire ? $this->validerDefunt() : null;

        if ($this->enMemoire) {
            // Les actions sont versées au Waqf caritatif, pas au donateur. La date d'ouverture
            // du compte institutionnel n'est pas réalignée : elle ne dépend pas d'un don reçu.
            $compte = Investisseur::waqfCaritatif()->compteOuCree('waqf');
        } else {
            // Le compte est créé automatiquement s'il n'existe pas encore (règle métier section 7)
            $compte = $this->investisseur->compteOuCree($this->categorie);

            // Si cet achat est antérieur à la date d'ouverture enregistrée, on aligne l'ouverture
            // du compte sur cette date réelle — important pour l'éligibilité aux dividendes passés.
            if ($this->date_achat < $compte->date_ouverture->toDateString()) {
                $compte->update(['date_ouverture' => $this->date_achat]);
            }
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
            'offert_par_investisseur_id' => $this->enMemoire ? $this->investisseur->id : null,
            'en_memoire_de' => $this->enMemoire ? $this->nomDuDefunt($defunt) : null,
            'en_memoire_investisseur_id' => $defunt?->id,
        ]);

        if ($this->enMemoire) {
            session()->flash('succes', "Achat {$achat->numero_achat} enregistré : {$this->nombre_actions} action(s) offerte(s) à la mémoire de {$achat->en_memoire_de}, versées au {$compte->numero_compte} (" . Investisseur::NOM_WAQF_CARITATIF . ').');
        } else {
            session()->flash('succes', "Achat {$achat->numero_achat} enregistré : {$this->nombre_actions} action(s) pour le compte {$compte->numero_compte}.");
        }

        \App\Models\AuditLog::enregistrer(
            action: 'creation',
            entite: 'achat',
            entiteId: $achat->id,
            apres: array_filter([
                'numero_achat' => $achat->numero_achat, 'compte' => $compte->numero_compte,
                'nombre_actions' => $achat->nombre_actions, 'montant' => (float) $achat->montant,
                'offert_par' => $this->enMemoire ? $this->investisseur->identifiant_externe : null,
                'en_memoire_de' => $achat->en_memoire_de,
            ], fn ($valeur) => $valeur !== null),
        );

        $this->redirectRoute('investisseurs.show', $this->investisseur, navigate: true);
    }

    public function render()
    {
        return view('livewire.achats.achat-create');
    }
}
