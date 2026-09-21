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
     * Present Waqf : le donateur paie, mais les actions vont au compte institutionnel
     * « Waqf Dolel Xamxam », jamais à son propre compte. Elle honore soit un défunt,
     * soit une personne vivante à qui l'on fait cadeau — dans les deux cas la personne
     * honorée n'est qu'une mention, sans droit patrimonial.
     */
    public bool $faireUnPresent = false;

    /** 'memoire' = hommage à un défunt, 'honneur' = cadeau à une personne vivante. */
    public string $typePresent = 'memoire';

    /** 'interne' = personne déjà enregistrée comme investisseur, 'externe' = personne extérieure. */
    public string $defuntSource = 'interne';

    public ?int $defuntInvestisseurId = null;

    public string $defuntNom = '';

    /** Lien du defunt avec le donateur (« Pere », « Mere », « Ami »...). Facultatif. */
    public string $lienAvecDonateur = '';

    public function mount(Investisseur $investisseur): void
    {
        $this->assurerAccesGestionnaire($investisseur);
        $this->investisseur = $investisseur;
        abort_if($investisseur->estDecede(), 403, __("Ce compte est gelé — l'investisseur est déclaré décédé. Gérez la succession depuis sa fiche."));
        $this->date_achat = now()->toDateString();
        $this->appliquerPrixParDefaut();
    }

    public function updatedCategorie(): void
    {
        $this->appliquerPrixParDefaut();

        // Un présent n'a de sens qu'en Waqf : le capital y est
        // inaliénable et sans versement de dividendes, donc réellement donné.
        if ($this->categorie !== 'waqf') {
            $this->faireUnPresent = false;
        }
    }

    /**
     * Les investisseurs sélectionnables comme personne honorée : les décédés pour un
     * hommage, les vivants pour un cadeau. Le donateur lui-même est exclu — on ne
     * s'offre pas un cadeau à soi-même, et le compte crédité ne serait pas le sien.
     */
    public function getDefuntsDisponiblesProperty()
    {
        $requete = $this->typePresent === 'memoire'
            ? Investisseur::where('statut', 'decede')
            : Investisseur::where('statut', '!=', 'decede')->where('id', '!=', $this->investisseur->id);

        return $requete->orderBy('nom')->orderBy('prenom')
            ->get(['id', 'nom', 'prenom', 'identifiant_externe', 'date_deces']);
    }

    /** Changer de motif invalide la personne déjà choisie : elle n'est plus éligible. */
    public function updatedTypePresent(): void
    {
        $this->defuntInvestisseurId = null;
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
     * Revalide côté serveur tout ce qui touche au présent : les propriétés Livewire sont
     * modifiables par le client, on ne se fie donc pas à l'affichage conditionnel de la vue.
     * Retourne le défunt quand c'est un investisseur de la plateforme, null s'il est extérieur.
     */
    protected function validerDefunt(): ?Investisseur
    {
        if ($this->categorie !== "waqf") {
            throw ValidationException::withMessages([
                "faireUnPresent" => __("Un présent n'est possible qu'en catégorie Waqf."),
            ]);
        }

        $this->validate([
            "typePresent" => ["required", "in:memoire,honneur"],
            "defuntSource" => ["required", "in:interne,externe"],
            "lienAvecDonateur" => ["nullable", "string", "max:100"],
        ], attributes: [
            "typePresent" => __("motif du présent"),
            "lienAvecDonateur" => __("lien avec le donateur"),
        ]);

        $hommage = $this->typePresent === "memoire";

        if ($this->defuntSource === "externe") {
            $this->validate([
                "defuntNom" => ["required", "string", "min:3", "max:150"],
            ], attributes: ["defuntNom" => $hommage ? __("nom du défunt") : __("nom du bénéficiaire")]);

            return null;
        }

        // Règle symétrique : un hommage vise un défunt, un cadeau une personne vivante.
        // Revalidée ici car la liste affichée n'engage que le navigateur.
        $regle = $hommage
            ? Rule::exists("investisseurs", "id")->where("statut", "decede")
            : Rule::exists("investisseurs", "id")->where(fn ($q) => $q->where("statut", "!=", "decede"));

        $this->validate([
            "defuntInvestisseurId" => ["required", $regle],
        ], messages: [
            "defuntInvestisseurId.required" => $hommage
                ? __("Sélectionnez le défunt honoré.")
                : __("Sélectionnez la personne à qui vous offrez ces actions."),
            "defuntInvestisseurId.exists" => $hommage
                ? __("Cet investisseur n'est pas déclaré décédé sur la plateforme.")
                : __("Cet investisseur est déclaré décédé — choisissez plutôt un hommage à sa mémoire."),
        ]);

        // On ne s'offre pas un cadeau à soi-même : la mention n'aurait aucun sens.
        if ((int) $this->defuntInvestisseurId === $this->investisseur->id) {
            throw ValidationException::withMessages([
                "defuntInvestisseurId" => __("Le donateur ne peut pas être le bénéficiaire de son propre présent."),
            ]);
        }

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

        $defunt = $this->faireUnPresent ? $this->validerDefunt() : null;

        if ($this->faireUnPresent) {
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
            'offert_par_investisseur_id' => $this->faireUnPresent ? $this->investisseur->id : null,
            'type_present' => $this->faireUnPresent ? $this->typePresent : null,
            'present_pour' => $this->faireUnPresent ? $this->nomDuDefunt($defunt) : null,
            'lien_avec_donateur' => $this->faireUnPresent ? (trim($this->lienAvecDonateur) ?: null) : null,
            'present_pour_investisseur_id' => $defunt?->id,
        ]);

        if ($this->faireUnPresent) {
            session()->flash('succes', __("Achat :numero enregistré : :actions action(s) offerte(s) :formule :beneficiaire, versées au :compte (:waqf).", ["numero" => $achat->numero_achat, "actions" => $this->nombre_actions, "formule" => $achat->formulePresent(), "beneficiaire" => $achat->present_pour, "compte" => $compte->numero_compte, "waqf" => Investisseur::NOM_WAQF_CARITATIF]));
        } else {
            session()->flash('succes', __("Achat :numero enregistré : :actions action(s) pour le compte :compte.", ["numero" => $achat->numero_achat, "actions" => $this->nombre_actions, "compte" => $compte->numero_compte]));
        }

        \App\Models\AuditLog::enregistrer(
            action: 'creation',
            entite: 'achat',
            entiteId: $achat->id,
            apres: array_filter([
                'numero_achat' => $achat->numero_achat, 'compte' => $compte->numero_compte,
                'nombre_actions' => $achat->nombre_actions, 'montant' => (float) $achat->montant,
                'offert_par' => $this->faireUnPresent ? $this->investisseur->identifiant_externe : null,
                'type_present' => $achat->type_present,
                'present_pour' => $achat->present_pour,
            ], fn ($valeur) => $valeur !== null),
        );

        $this->redirectRoute('investisseurs.show', $this->investisseur, navigate: true);
    }

    public function render()
    {
        return view('livewire.achats.achat-create');
    }
}
