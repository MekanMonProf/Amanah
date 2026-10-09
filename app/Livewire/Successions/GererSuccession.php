<?php

namespace App\Livewire\Successions;

use App\Livewire\Concerns\ReplieSesBlocs;
use App\Models\CompteInvestissement;
use App\Models\Don;
use App\Models\Heritier;
use App\Models\Investisseur;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
class GererSuccession extends Component
{
    use ReplieSesBlocs;

    use WithFileUploads;

    public Investisseur $investisseur;

    public bool $afficherFormulaire = false;

    #[Validate('required|string|max:150')]
    public string $nom = '';

    #[Validate('nullable|string|max:150')]
    public string $prenom = '';

    #[Validate('nullable|string|max:30')]
    public string $telephone = '';
    public string $whatsapp = '';

    /** Le WhatsApp est presque toujours le numero de telephone : coche par defaut. */
    public bool $whatsappIdentique = true;

    #[Validate('required|string|max:100')]
    public string $lienParente = '';

    // Les trois pièces du mandataire signalent, elles ne bloquent plus : c'est la
    // règle de toute la plateforme, et une succession s'ouvre souvent avant que la
    // famille n'ait réuni ses papiers. Le paramétrage décide lesquelles comptent
    // dans le signalement « dossier incomplet » ; voir App\Support\Completude.
    #[Validate('nullable|file|mimes:jpg,jpeg,png,pdf|max:5120')]
    public $pieceIdentiteUpload = null;

    #[Validate('nullable|file|mimes:jpg,jpeg,png,pdf|max:5120')]
    public $certificatHeritedeUpload = null;

    #[Validate('nullable|file|mimes:jpg,jpeg,png,pdf|max:5120')]
    public $procurationUpload = null;

    public string $modeReglementCommercial = 'transfert'; // 'transfert' | 'paiement'

    public ?array $resultatTransfert = null;

    public function mount(Investisseur $investisseur): void
    {
        $this->investisseur = $investisseur;
        $this->reprendreLesBlocsReplies();
    }

    protected function cleDeSessionDesBlocs(): string
    {
        return 'amanah.blocs_replies_succession';
    }

    /** Les pièces n'existent qu'une fois la succession réglée : on les y trouve, on ne les y cherche pas. */
    protected function repliesParDefaut(): array
    {
        return ['documents' => true];
    }

    public function getMandataireProperty(): ?Heritier
    {
        return $this->investisseur->heritiers()->with('investisseurHeritier')->first();
    }

    /**
     * Aperçu en lecture seule de ce qui sera fait par compte — calculé sans rien écrire en
     * base, pour que l'utilisateur valide en connaissance de cause avant l'action définitive.
     */
    public function getApercuProperty(): array
    {
        $mandataire = $this->mandataire;
        if (! $mandataire) {
            return [];
        }

        $lignes = [];

        foreach ($this->investisseur->comptes as $compte) {
            $nbActions = $compte->nombreActions();
            $solde = $compte->solde();

            if ($nbActions <= 0 && $solde <= 0) {
                continue;
            }

            $prixUnitaire = (float) ($compte->politique()?->prix_unitaire_action ?? 0);
            $montantActions = $nbActions * $prixUnitaire;

            if ($compte->categorie === 'waqf') {
                $destination = __(":waqf (œuvre caritative)", ['waqf' => Investisseur::NOM_WAQF_CARITATIF]);
            } elseif ($this->modeReglementCommercial === 'paiement') {
                $destination = __("Paiement direct — :mandataire", ['mandataire' => $mandataire->nom . ' ' . $mandataire->prenom]);
            } else {
                $destination = __(":mandataire (compte investisseur)", ['mandataire' => $mandataire->nom . ' ' . $mandataire->prenom]);
            }

            $lignes[] = [
                'compte' => $compte->numero_compte,
                'categorie' => $compte->categorie,
                'destination' => $destination,
                'nb_actions' => $nbActions,
                'prix_unitaire' => $prixUnitaire,
                'montant_actions' => $montantActions,
                'solde' => $solde,
                'total' => $montantActions + $solde,
            ];
        }

        return $lignes;
    }

    public function designerMandataire(): void
    {
        $this->validate();

        $cheminIdentite = $this->pieceIdentiteUpload?->store('successions', 'local');
        $cheminCertificat = $this->certificatHeritedeUpload?->store('successions', 'local');
        $cheminProcuration = $this->procurationUpload?->store('successions', 'local');

        Heritier::create([
            'investisseur_id' => $this->investisseur->id,
            'nom' => $this->nom,
            'prenom' => $this->prenom ?: null,
            'telephone' => \App\Support\Telephone::normaliser($this->telephone),
            'whatsapp' => \App\Support\Telephone::pourWhatsapp($this->whatsappIdentique, $this->telephone, $this->whatsapp),
            'lien_parente' => $this->lienParente,
            'part_pourcentage' => 100,
            'piece_identite_path' => $cheminIdentite,
            'piece_justificative_path' => $cheminProcuration,
            'piece_certificat_heredite_path' => $cheminCertificat,
        ]);

        \App\Models\AuditLog::enregistrer(
            action: 'designation_mandataire_succession',
            entite: 'investisseur',
            entiteId: $this->investisseur->id,
            apres: ['mandataire' => $this->nom . ' ' . $this->prenom, 'lien' => $this->lienParente],
        );

        $this->reset(['nom', 'prenom', 'telephone', 'whatsapp', 'whatsappIdentique', 'lienParente', 'pieceIdentiteUpload', 'certificatHeritedeUpload', 'procurationUpload', 'afficherFormulaire']);
    }

    public function retirerMandataire(): void
    {
        $this->investisseur->heritiers()->delete();
    }

    protected function nomDefunt(): string
    {
        return trim($this->investisseur->nom . ' ' . $this->investisseur->prenom);
    }

    protected function compteWaqfCaritatif(): CompteInvestissement
    {
        return Investisseur::waqfCaritatif()->compteOuCree('waqf');
    }

    protected function creerDossierMandataire(Heritier $mandataire): void
    {
        $nouvelInvestisseur = Investisseur::create([
            'identifiant_externe' => Investisseur::prochainIdentifiant(),
            'type_personne' => 'physique',
            'nom' => $mandataire->nom,
            'prenom' => $mandataire->prenom,
            'telephone' => $mandataire->telephone,
            'gestionnaire_id' => $this->investisseur->gestionnaire_id,
            'statut' => 'actif',
            'notes_internes' => "Dossier créé automatiquement en tant que mandataire de la succession de {$this->nomDefunt()} ({$this->investisseur->identifiant_externe}).",
        ]);

        $mandataire->update(['investisseur_heritier_id' => $nouvelInvestisseur->id]);
        $mandataire->refresh();
    }

    /**
     * Transfert interne (don de type succession) — les libellés mentionnent systématiquement
     * le défunt, pour qu'un don "par décès" se distingue clairement d'un don classique entre
     * investisseurs vivants dans tout l'historique.
     */
    protected function transfererVersCompte(CompteInvestissement $source, CompteInvestissement $destination, Heritier $mandataire, int $nbActions, float $montant, string $libelleDestination): void
    {
        $defunt = $this->nomDefunt();
        $motifDon = "Don par décès de {$defunt} — {$libelleDestination}";

        if ($nbActions > 0) {
            Don::create([
                'compte_source_id' => $source->id,
                'compte_destinataire_id' => $destination->id,
                'type_don' => 'actions',
                'type_operation' => 'succession',
                'heritier_id' => $mandataire->id,
                'nombre_actions' => $nbActions,
                'prix_unitaire_action' => $source->politique()?->prix_unitaire_action,
                'date_don' => now()->toDateString(),
                'motif' => $motifDon,
                'created_by' => Auth::id(),
            ]);
        }

        if ($montant > 0) {
            $don = Don::create([
                'compte_source_id' => $source->id,
                'compte_destinataire_id' => $destination->id,
                'type_don' => 'solde',
                'type_operation' => 'succession',
                'heritier_id' => $mandataire->id,
                'montant' => $montant,
                'date_don' => now()->toDateString(),
                'motif' => $motifDon,
                'created_by' => Auth::id(),
            ]);

            $source->ajouterEcriture(
                type: 'don_sortant',
                montant: -$montant,
                dateEcriture: now()->toDateString(),
                referenceType: 'dons',
                referenceId: $don->id,
                observationCle: \App\Support\Observation::DON_SORTANT_DECES,
                observationParametres: ['defunt' => $defunt],
                userId: Auth::id(),
            );

            $destination->ajouterEcriture(
                type: 'don_entrant',
                montant: $montant,
                dateEcriture: now()->toDateString(),
                referenceType: 'dons',
                referenceId: $don->id,
                observationCle: \App\Support\Observation::DON_ENTRANT_DECES,
                observationParametres: ['defunt' => $defunt],
                userId: Auth::id(),
            );

            // Réinvestissement, avec un libellé qui indique explicitement l'origine successorale —
            // pour que l'achat qui en résulte ne se confonde pas avec un réinvestissement ordinaire.
            $destination->tenterReinvestissementAutomatique(
                Auth::id(),
                \App\Support\Observation::REINVESTISSEMENT_SUCCESSION,
                ['defunt' => $defunt],
            );
        }
    }

    /**
     * Liquide les actions du compte (comme une radiation) et laisse l'argent disponible
     * sur le compte du défunt — le versement effectif se fait ensuite via un écran dédié
     * (mode de paiement, justificatif), pas automatiquement ici.
     */
    protected function liquiderActions(CompteInvestissement $compteDefunt): int
    {
        $defunt = $this->nomDefunt();
        $nbActions = $compteDefunt->nombreActions();

        if ($nbActions > 0) {
            $prixUnitaire = (float) ($compteDefunt->politique()?->prix_unitaire_action ?? 0);
            $montantActions = $nbActions * $prixUnitaire;

            $radiation = $compteDefunt->radiations()->create([
                'numero_radiation' => 'RAD-SUCC-' . $compteDefunt->id . '-' . now()->format('YmdHis'),
                'date_radiation' => now()->toDateString(),
                'nombre_actions_radiees' => $nbActions,
                'prix_unitaire_action' => $prixUnitaire,
                'montant_total' => $montantActions,
                'observations' => \App\Support\Observation::francais(
                    \App\Support\Observation::LIQUIDATION_RADIATION, ['defunt' => $defunt],
                ),
                'observation_cle' => \App\Support\Observation::LIQUIDATION_RADIATION,
                'observation_parametres' => ['defunt' => $defunt],
            ]);

            $compteDefunt->ajouterEcriture(
                type: 'radiation',
                montant: $montantActions,
                dateEcriture: now()->toDateString(),
                referenceType: 'radiations',
                referenceId: $radiation->id,
                observationCle: \App\Support\Observation::LIQUIDATION_SUCCESSION,
                observationParametres: ['defunt' => $defunt, 'actions' => $nbActions],
                userId: Auth::id(),
            );
        }

        return $nbActions;
    }

    public function reglerSuccession(): void
    {
        $mandataire = $this->mandataire;
        if (! $mandataire) {
            return;
        }

        $resultat = [];

        DB::transaction(function () use ($mandataire, &$resultat) {
            foreach ($this->investisseur->comptes as $compteDefunt) {
                $nbActions = $compteDefunt->nombreActions();
                $soldeInitial = $compteDefunt->solde();

                if ($nbActions <= 0 && $soldeInitial <= 0) {
                    continue;
                }

                if ($compteDefunt->categorie === 'waqf') {
                    $compteCaritatif = $this->compteWaqfCaritatif();
                    $this->transfererVersCompte($compteDefunt, $compteCaritatif, $mandataire, $nbActions, $soldeInitial, "capital redirigé vers l'œuvre caritative Waqf Dolel Xamxam");

                    $resultat[] = ['compte' => $compteDefunt->numero_compte, 'destination' => __(":waqf (œuvre caritative)", ['waqf' => Investisseur::NOM_WAQF_CARITATIF]), 'actions' => $nbActions, 'montant' => $soldeInitial];
                    continue;
                }

                if ($this->modeReglementCommercial === 'paiement') {
                    $actionsLiquidees = $this->liquiderActions($compteDefunt);
                    $resultat[] = ['compte' => $compteDefunt->numero_compte, 'destination' => __("En attente de versement — :mandataire", ['mandataire' => $mandataire->nom . ' ' . $mandataire->prenom]), 'actions' => $actionsLiquidees, 'montant' => $compteDefunt->solde()];
                } else {
                    if (! $mandataire->investisseur_heritier_id) {
                        $this->creerDossierMandataire($mandataire);
                    }
                    $compteMandataire = $mandataire->investisseurHeritier->compteOuCree('commercial');
                    $this->transfererVersCompte($compteDefunt, $compteMandataire, $mandataire, $nbActions, $soldeInitial, "transmis au mandataire {$mandataire->nom} {$mandataire->prenom}");

                    $resultat[] = ['compte' => $compteDefunt->numero_compte, 'destination' => __(":mandataire (compte investisseur)", ['mandataire' => $mandataire->nom . ' ' . $mandataire->prenom]), 'actions' => $nbActions, 'montant' => $soldeInitial];
                }
            }

            $this->investisseur->update(['succession_reglee' => true]);

            \App\Models\AuditLog::enregistrer(
                action: 'reglement_succession',
                entite: 'investisseur',
                entiteId: $this->investisseur->id,
                apres: ['mandataire' => $mandataire->nom . ' ' . $mandataire->prenom, 'mode_commercial' => $this->modeReglementCommercial],
            );
        });

        $this->resultatTransfert = $resultat;
    }

    /**
     * Ce que la succession met en jeu, avant même qu'on désigne qui que ce soit.
     *
     * L'aperçu compte par compte n'apparaît qu'une fois le mandataire désigné.
     * Or la première question posée au téléphone est « combien cela
     * représente-t-il » : elle doit trouver sa réponse en haut de l'écran.
     *
     * @return array{comptes:int, commerciales:int, waqf:int, solde:float}
     */
    public function getPositionProperty(): array
    {
        $comptes = $this->investisseur->comptes()->get();

        return [
            'comptes' => $comptes->count(),
            'commerciales' => $comptes->where('categorie', 'commercial')->sum(fn ($compte) => $compte->nombreActions()),
            'waqf' => $comptes->where('categorie', 'waqf')->sum(fn ($compte) => $compte->nombreActions()),
            'solde' => $comptes->sum(fn ($compte) => $compte->solde()),
        ];
    }

    /**
     * Les pièces que la succession a produites : l'attestation de liquidation
     * et celle du versement aux ayants droit. Le reste des documents du dossier
     * — attestations d'achat, reçus — se consulte sur la fiche ; ici on ne
     * cherche que ce qui atteste du règlement.
     */
    public function getDocumentsProperty()
    {
        return \App\Support\DocumentsDuDossier::pour($this->investisseur)
            ->whereIn('famille', ['succession', 'deces'])
            ->values();
    }

    public function render()
    {
        return view('livewire.successions.gerer-succession');
    }
}
