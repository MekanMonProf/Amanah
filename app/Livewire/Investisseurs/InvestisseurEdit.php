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
    public string $whatsapp = '';

    /** Le WhatsApp est presque toujours le numero de telephone : coche par defaut. */
    public bool $whatsappIdentique = true;

    #[Validate('nullable|string|max:255')]
    public string $adresse = '';

    #[Validate('nullable|string|max:100')]
    public string $ville = '';

    #[Validate('nullable|string|max:100')]
    public string $pays = '';

    #[Validate('nullable|string|max:100')]
    public string $nationalite = '';

    /**
     * La langue dans laquelle l'investisseur reçoit ce que la plateforme lui
     * adresse — aujourd'hui le message WhatsApp qui transmet ses identifiants,
     * et l'interface du portail s'il en a un.
     */
    #[Validate('required|in:fr,en,ar')]
    public string $langue = \App\Support\Langue::DEFAUT;

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

    /** Champs lus sur la piece et actuellement vides dans le formulaire. */
    public array $propositionsPiece = [];

    /**
     * Valeurs lues qui ne portent pas le nom d un champ du formulaire.
     *
     * Le numero imprime au-dessus de la bande est propose a part de celui que
     * porte la bande : sur la carte senegalaise les deux existent et different.
     */
    private const CHAMPS_LUS = ['numero_imprime' => 'numero_identification'];

    /** Champs lus qui contredisent ce qui est deja saisi — signales, jamais appliques. */
    public array $divergencesPiece = [];

    /**
     * Champs lus dont la cle de controle n a pas confirme la lecture. Montres
     * pour que le gestionnaire les compare a la piece, jamais appliques d office.
     */
    public array $nonConfirmesPiece = [];

    /** Pourquoi la lecture n a rien donne, le cas echeant. */
    public ?string $motifLecturePiece = null;

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
    public string $representant_legal_whatsapp = '';
    public bool $representantWhatsappIdentique = true;

    // Bénéficiaire désigné
    #[Validate('nullable|string|max:150')]
    public string $beneficiaire_nom = '';

    #[Validate('nullable|string|max:100')]
    public string $beneficiaire_lien = '';

    #[Validate('nullable|string|max:30')]
    public string $beneficiaire_telephone = '';
    public string $beneficiaire_whatsapp = '';
    public bool $beneficiaireWhatsappIdentique = true;

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
        'type_personne', 'nom', 'prenom', 'email', 'telephone', 'whatsapp', 'adresse', 'ville', 'pays',
        'nationalite', 'lieu_naissance', 'type_identification', 'numero_identification', 'lieu_delivrance_piece',
        'raison_sociale', 'rccm', 'ninea', 'representant_legal_nom', 'representant_legal_telephone', 'representant_legal_whatsapp',
        'beneficiaire_nom', 'beneficiaire_lien', 'beneficiaire_telephone', 'beneficiaire_whatsapp', 'notes_internes',
    ];

    /**
     * Des que la piece est jointe, on tente d en lire la bande MRZ.
     *
     * Rien n est ecrit d autorite : les valeurs lues sont proposees, et celles
     * qui contredisent une saisie existante sont seulement signalees. Sur un
     * dossier d identification, un ecart entre la piece et le formulaire est
     * precisement ce qu on veut voir, pas ce qu on veut effacer.
     */
    public function updatedPieceIdentiteUpload(): void
    {
        $this->propositionsPiece = [];
        $this->divergencesPiece = [];
        $this->nonConfirmesPiece = [];
        $this->motifLecturePiece = null;

        if (! $this->piece_identite_upload) {
            return;
        }

        $this->validateOnly('piece_identite_upload');

        $lecture = \App\Support\Piece\LecteurPiece::lire($this->piece_identite_upload->getRealPath());

        if ($lecture['format'] === null) {
            $this->motifLecturePiece = $lecture['motif'];

            return;
        }

        foreach ($lecture['champs'] as $champ => $valeur) {
            if (! property_exists($this, $champ)) {
                continue;
            }

            if (blank($this->$champ)) {
                $this->propositionsPiece[$champ] = $valeur;
            } elseif (mb_strtolower(trim((string) $this->$champ)) !== mb_strtolower($valeur)) {
                $this->divergencesPiece[$champ] = $valeur;
            }
        }

        // Une valeur dont la cle n a pas confirme la lecture n est pas proposee,
        // mais elle est montree : sans cela le champ reste vide sans que rien ne
        // dise si la lecture a eu lieu ou non — c est ce qui s est passe au
        // premier essai sur une vraie piece.
        foreach ($lecture['ecartes'] as $champ => $valeur) {
            $cible = self::CHAMPS_LUS[$champ] ?? $champ;

            if (property_exists($this, $cible) && blank($this->$cible)) {
                $this->nonConfirmesPiece[$champ] = $valeur;
            }
        }
    }

    /**
     * Recopie une valeur non confirmee, apres que le gestionnaire l a comparee a
     * la piece. La cle de controle protege d une corruption silencieuse ; un
     * humain qui lit la valeur a l ecran et la valide n a rien de silencieux.
     */
    public function accepterNonConfirme(string $champ): void
    {
        $cible = self::CHAMPS_LUS[$champ] ?? $champ;

        if (isset($this->nonConfirmesPiece[$champ]) && property_exists($this, $cible)) {
            $this->$cible = $this->nonConfirmesPiece[$champ];
            unset($this->nonConfirmesPiece[$champ]);
        }
    }

    /** Recopie les valeurs proposees dans le formulaire. L enregistrement reste a faire. */
    public function appliquerPropositionsPiece(): void
    {
        foreach ($this->propositionsPiece as $champ => $valeur) {
            if (property_exists($this, $champ)) {
                $this->$champ = $valeur;
            }
        }

        $this->propositionsPiece = [];
    }

    public function ignorerPropositionsPiece(): void
    {
        $this->reset(['propositionsPiece', 'divergencesPiece', 'nonConfirmesPiece', 'motifLecturePiece']);
    }

    public function mount(Investisseur $investisseur): void
    {
        $this->assurerAccesGestionnaire($investisseur);
        abort_if($investisseur->estDecede(), 403, __("Ce compte est gelé — l'investisseur est déclaré décédé. Gérez la succession depuis sa fiche."));
        $this->investisseur = $investisseur;

        $valeurs = $investisseur->only($this->champsTexte);

        foreach ($this->champsTexte as $champ) {
            $this->$champ = $valeurs[$champ] ?? '';
        }

        // La case « meme numero » reflete ce qui est en base : elle ne doit pas
        // rester cochee sur un dossier ou les deux numeros different reellement.
        $this->whatsappIdentique = \App\Support\Telephone::memeNumero($investisseur->telephone, $investisseur->whatsapp);
        $this->representantWhatsappIdentique = \App\Support\Telephone::memeNumero($investisseur->representant_legal_telephone, $investisseur->representant_legal_whatsapp);
        $this->beneficiaireWhatsappIdentique = \App\Support\Telephone::memeNumero($investisseur->beneficiaire_telephone, $investisseur->beneficiaire_whatsapp);

        // Hors de $champsTexte : une valeur vide n'a pas de sens ici, et un code
        // inconnu retombe sur le français plutôt que de vider le sélecteur.
        $this->langue = \App\Support\Langue::normaliser($investisseur->langue);

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

        // Un numéro étranger dont l'indicatif n'est pas reconnaissable (ex: un
        // français en 0612345678) ne doit jamais être accepté en silence — voir
        // App\Support\Telephone. Ce champ sert d'identifiant de connexion, la
        // vérification est donc plus stricte ici que sur les contacts secondaires
        // (bénéficiaire, représentant légal) ci-dessous.
        if (\App\Support\Telephone::estAmbigu($this->telephone)) {
            $this->addError('telephone', __("Ce numéro semble étranger : précisez l'indicatif pays devant (ex : +33 pour la France, +221 pour le Sénégal)."));
            return;
        }

        $donnees = [
            'type_personne' => $this->type_personne,
            'nom' => $this->nom,
            'prenom' => $this->prenom ?: null,
            'email' => $this->email ?: null,
            'telephone' => \App\Support\Telephone::normaliser($this->telephone),
            'whatsapp' => \App\Support\Telephone::pourWhatsapp($this->whatsappIdentique, $this->telephone, $this->whatsapp),
            'adresse' => $this->adresse ?: null,
            'ville' => $this->ville ?: null,
            'pays' => $this->pays ?: null,
            'nationalite' => $this->nationalite ?: null,
            'langue' => \App\Support\Langue::normaliser($this->langue),
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
            'representant_legal_whatsapp' => \App\Support\Telephone::pourWhatsapp($this->representantWhatsappIdentique, $this->representant_legal_telephone, $this->representant_legal_whatsapp),
            'beneficiaire_nom' => $this->beneficiaire_nom ?: null,
            'beneficiaire_lien' => $this->beneficiaire_lien ?: null,
            'beneficiaire_telephone' => \App\Support\Telephone::normaliser($this->beneficiaire_telephone),
            'beneficiaire_whatsapp' => \App\Support\Telephone::pourWhatsapp($this->beneficiaireWhatsappIdentique, $this->beneficiaire_telephone, $this->beneficiaire_whatsapp),
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

        session()->flash('succes', __("Dossier mis à jour avec succès."));

        $this->redirectRoute('investisseurs.show', $this->investisseur, navigate: true);
    }

    public function render()
    {
        return view('livewire.investisseurs.investisseur-edit');
    }
}
