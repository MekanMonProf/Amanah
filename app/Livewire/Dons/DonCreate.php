<?php

namespace App\Livewire\Dons;

use App\Models\CompteInvestissement;
use App\Models\Don;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
class DonCreate extends Component
{
    use WithFileUploads;
    use \App\Support\RestreintAuPortefeuilleGestionnaire;

    public CompteInvestissement $compteSource;

    #[Validate('required|in:actions,solde')]
    public string $typeDon = 'actions';

    public string $rechercheDestinataire = '';
    public ?int $compteDestinataireId = null;
    public ?string $nomDestinataireChoisi = null;

    #[Validate('nullable|integer|min:1')]
    public ?int $nombreActions = null;

    #[Validate('nullable|numeric|min:1')]
    public ?float $montant = null;

    #[Validate('required|date')]
    public string $dateDon;

    #[Validate('required|string|min:15|max:1000')]
    public string $motif = '';

    #[Validate('nullable|file|mimes:jpg,jpeg,png,pdf|max:5120')]
    public $pieceJustificativeUpload = null;

    public function mount(CompteInvestissement $compte): void
    {
        $this->assurerAccesGestionnairePourCompte($compte);
        $this->compteSource = $compte;
        $this->dateDon = now()->toDateString();

        abort_if($compte->investisseur->estDecede(), 403, __("Ce compte est gelé — l'investisseur est déclaré décédé. Gérez la succession depuis sa fiche."));

        $this->interdireSiCessionRefusee();
    }

    /**
     * Un compte dont la catégorie interdit la cession ne donne rien, ni parts ni
     * solde.
     *
     * La règle était affichée depuis toujours — « Cession autorisée : Non » sur
     * un waqf — mais aucun code ne la lisait : un compte waqf pouvait donner ses
     * actions à un autre compte waqf. Annoncer l'inaliénabilité du capital sans
     * l'appliquer est pire que de ne rien annoncer.
     *
     * Le solde suit les parts : les bénéfices d'un waqf ne se versent pas à son
     * titulaire, ils ne se donnent pas davantage.
     */
    protected function interdireSiCessionRefusee(): void
    {
        $autorisee = (bool) ($this->compteSource->politique()?->cession_autorisee ?? true);

        abort_unless($autorisee, 403, __(
            "La catégorie :categorie n'autorise pas la cession : le capital y est immobilisé, ni les parts ni le solde ne peuvent être donnés.",
            ['categorie' => \App\Support\Libelles::categorie($this->compteSource->categorie)],
        ));
    }

    public function getResultatsRechercheProperty()
    {
        if (strlen($this->rechercheDestinataire) < 2) {
            return collect();
        }

        return CompteInvestissement::where('categorie', $this->compteSource->categorie)
            ->where('id', '!=', $this->compteSource->id)
            ->where('statut', 'actif')
            ->whereHas('investisseur', function ($q) {
                $q->where('nom', 'like', "%{$this->rechercheDestinataire}%")
                  ->orWhere('prenom', 'like', "%{$this->rechercheDestinataire}%")
                  ->orWhere('identifiant_externe', 'like', "%{$this->rechercheDestinataire}%");
            })
            ->with('investisseur')
            ->take(8)
            ->get();
    }

    public function choisirDestinataire(int $compteId): void
    {
        $compte = CompteInvestissement::with('investisseur')->findOrFail($compteId);

        // La recherche affichée ne propose déjà que la même catégorie, mais cette méthode
        // est appelable directement (requête Livewire forgée) — on revalide donc ici aussi.
        abort_if($compte->categorie !== $this->compteSource->categorie, 403, __("Le destinataire doit être de la même catégorie que le compte source."));
        abort_if($compte->id === $this->compteSource->id, 403);

        $this->compteDestinataireId = $compte->id;
        $this->nomDestinataireChoisi = $compte->investisseur->nom . ' ' . $compte->investisseur->prenom . ' (' . $compte->numero_compte . ')';
        $this->rechercheDestinataire = '';
    }

    public function retirerDestinataire(): void
    {
        $this->compteDestinataireId = null;
        $this->nomDestinataireChoisi = null;
    }

    public function enregistrer(): void
    {
        // Revalidé ici et pas seulement au chargement : rien n'empêche une requête
        // forgée d'appeler directement cette méthode.
        $this->interdireSiCessionRefusee();

        $this->validate();

        if (! $this->compteDestinataireId) {
            $this->addError('rechercheDestinataire', __("Choisissez un investisseur destinataire."));
            return;
        }

        // compteDestinataireId est une propriété Livewire publique, potentiellement falsifiable
        // via une requête forgée — on revalide donc la règle métier ici, pas seulement à la
        // sélection dans choisirDestinataire().
        $categorieDestinataire = CompteInvestissement::where('id', $this->compteDestinataireId)->value('categorie');
        if ($categorieDestinataire !== $this->compteSource->categorie || $this->compteDestinataireId === $this->compteSource->id) {
            $this->addError('rechercheDestinataire', __("Le destinataire doit être de la même catégorie que le compte source."));
            return;
        }

        if ($this->typeDon === 'actions') {
            if (! $this->nombreActions) {
                $this->addError('nombreActions', __("Indiquez le nombre d'actions à donner."));
                return;
            }
            if ($this->nombreActions > $this->compteSource->nombreActions()) {
                $this->addError('nombreActions', __("Impossible de donner plus d'actions que détenues (:detenues).", ['detenues' => $this->compteSource->nombreActions()]));
                return;
            }
        } else {
            if (! $this->montant) {
                $this->addError('montant', __("Indiquez le montant à donner."));
                return;
            }
            if ($this->montant > $this->compteSource->solde()) {
                $this->addError('montant', __("Le montant dépasse le solde disponible (:solde).", ['solde' => \App\Support\Montant::avecDevise($this->compteSource->solde())]));
                return;
            }
        }

        $compteDestinataire = CompteInvestissement::findOrFail($this->compteDestinataireId);

        $cheminPiece = null;
        if ($this->pieceJustificativeUpload) {
            $cheminPiece = $this->pieceJustificativeUpload->store('dons', 'local');
        }

        DB::transaction(function () use ($compteDestinataire, $cheminPiece) {
            $don = Don::create([
                'compte_source_id' => $this->compteSource->id,
                'compte_destinataire_id' => $compteDestinataire->id,
                'type_don' => $this->typeDon,
                'nombre_actions' => $this->typeDon === 'actions' ? $this->nombreActions : null,
                'prix_unitaire_action' => $this->typeDon === 'actions' ? $this->compteSource->politique()?->prix_unitaire_action : null,
                'montant' => $this->typeDon === 'solde' ? $this->montant : null,
                'date_don' => $this->dateDon,
                'motif' => $this->motif,
                'piece_justificative_path' => $cheminPiece,
                'created_by' => Auth::id(),
            ]);

            if ($this->typeDon === 'solde') {
                $this->compteSource->ajouterEcriture(
                    type: 'don_sortant',
                    montant: -$this->montant,
                    dateEcriture: $this->dateDon,
                    referenceType: 'dons',
                    referenceId: $don->id,
                    observationCle: \App\Support\Observation::DON_SORTANT,
                    observationParametres: ['beneficiaire' => $compteDestinataire->investisseur->nom, 'motif' => $this->motif],
                    userId: Auth::id(),
                );

                $compteDestinataire->ajouterEcriture(
                    type: 'don_entrant',
                    montant: $this->montant,
                    dateEcriture: $this->dateDon,
                    referenceType: 'dons',
                    referenceId: $don->id,
                    observationCle: \App\Support\Observation::DON_ENTRANT,
                    observationParametres: ['donateur' => $this->compteSource->investisseur->nom, 'motif' => $this->motif],
                    userId: Auth::id(),
                );

                $compteDestinataire->tenterReinvestissementAutomatique(Auth::id());
            }

            \App\Models\AuditLog::enregistrer(
                action: 'don',
                entite: 'compte_investissement',
                entiteId: $this->compteSource->id,
                apres: [
                    'de' => $this->compteSource->numero_compte,
                    'vers' => $compteDestinataire->numero_compte,
                    'type' => $this->typeDon,
                    'nombre_actions' => $this->nombreActions,
                    'montant' => $this->montant,
                    'motif' => $this->motif,
                ],
            );
        });

        session()->flash('succes', __("Don enregistré avec succès."));

        $this->redirectRoute('investisseurs.show', $this->compteSource->investisseur, navigate: true);
    }

    public function render()
    {
        return view('livewire.dons.don-create');
    }
}
