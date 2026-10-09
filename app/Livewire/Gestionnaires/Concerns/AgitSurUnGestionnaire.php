<?php

namespace App\Livewire\Gestionnaires\Concerns;

use App\Models\Gestionnaire;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Validate;

/**
 * Ce qu'on peut faire à un gestionnaire, où qu'on se trouve.
 *
 * Ces actions vivaient dans l'écran de liste, seul endroit d'où l'on pouvait
 * les atteindre. La fiche d'un gestionnaire les offre maintenant aussi, et les
 * dupliquer aurait créé deux versions d'une même règle — celle qui refuse de
 * désactiver un gestionnaire dont le portefeuille n'est pas vide, par exemple,
 * qui ne doit pas dépendre de l'écran par lequel on passe.
 *
 * La création d'un compte, elle, reste à la liste : on ne crée pas un
 * gestionnaire depuis la fiche d'un autre.
 */
trait AgitSurUnGestionnaire
{
    #[Validate('required|string|max:150')]
    public string $nom = '';

    #[Validate('nullable|string|max:150')]
    public string $prenom = '';

    #[Validate('required|email|max:190|unique:users,email')]
    public string $email = '';

    #[Validate('nullable|string|max:30')]
    public string $telephone = '';
    public string $whatsapp = '';

    /** Le WhatsApp est presque toujours le numero de telephone : coche par defaut. */
    public bool $whatsappIdentique = true;

    public ?string $dernierMotDePasseGenere = null;
    public ?string $emailConcerneParReinit = null;

    public ?int $gestionnaireEnEditionId = null;

    public ?int $gestionnaireADesactiverId = null;
    public ?int $nouveauGestionnairePourReassignation = null;
    public string $motifReassignationMasse = '';

    /**
     * Une fiche gestionnaire dont le compte de connexion a ete supprime ne peut
     * plus etre modifiee : il n y a plus de nom a changer, ni d adresse ou
     * envoyer un mot de passe. La reassignation de son portefeuille, elle, reste
     * autorisee — c est justement la seule issue.
     */
    private function refuserSiOrphelin(Gestionnaire $gestionnaire): bool
    {
        if (! $gestionnaire->estOrphelin()) {
            return false;
        }

        session()->flash('erreur_orphelin', __(
            "Cette fiche gestionnaire n'a plus de compte de connexion : elle ne peut plus être modifiée. Réassignez son portefeuille à un autre gestionnaire.",
        ));

        return true;
    }

    public function modifier(int $gestionnaireId): void
    {
        $gestionnaire = Gestionnaire::with('user')->findOrFail($gestionnaireId);

        if ($this->refuserSiOrphelin($gestionnaire)) {
            return;
        }

        $this->gestionnaireEnEditionId = $gestionnaireId;
        $this->nom = $gestionnaire->user->nom;
        $this->prenom = $gestionnaire->user->prenom ?? '';
        $this->email = $gestionnaire->user->email;
        $this->telephone = $gestionnaire->user->telephone ?? '';
        $this->whatsapp = $gestionnaire->user->whatsapp ?? '';
        $this->whatsappIdentique = \App\Support\Telephone::memeNumero($gestionnaire->user->telephone, $gestionnaire->user->whatsapp);
        $this->fermerLeFormulaireDeCreation();
        $this->resetErrorBag();
    }

    /**
     * Ouvrir une modification referme la création : les deux formulaires
     * partagent les mêmes champs, et les voir tous deux ouverts ne dirait pas
     * lequel on est en train de remplir.
     *
     * La fiche d'un gestionnaire n'a pas de formulaire de création — on n'en
     * crée pas un depuis la fiche d'un autre —, d'où ce point d'accroche vide
     * plutôt qu'une propriété que seule la liste porterait.
     */
    protected function fermerLeFormulaireDeCreation(): void
    {
        //
    }

    public function annulerModification(): void
    {
        $this->gestionnaireEnEditionId = null;
        $this->reset(['nom', 'prenom', 'email', 'telephone']);
    }

    public function enregistrerModification(): void
    {
        $gestionnaire = Gestionnaire::with('user')->findOrFail($this->gestionnaireEnEditionId);

        if ($this->refuserSiOrphelin($gestionnaire)) {
            return;
        }

        $this->validate([
            'nom' => 'required|string|max:150',
            'prenom' => 'nullable|string|max:150',
            'email' => 'required|email|max:190|unique:users,email,' . $gestionnaire->user_id,
            'telephone' => 'nullable|string|max:30',
            'whatsapp' => 'nullable|string|max:30',
        ]);

        if (\App\Support\Telephone::estAmbigu($this->telephone)) {
            $this->addError('telephone', __("Ce numéro semble étranger : précisez l'indicatif pays devant (ex : +33 pour la France, +221 pour le Sénégal)."));

            return;
        }

        $avant = $gestionnaire->user->only(['nom', 'prenom', 'email', 'telephone']);

        $gestionnaire->user->update([
            'nom' => $this->nom,
            'prenom' => $this->prenom ?: null,
            'email' => $this->email,
            'telephone' => \App\Support\Telephone::normaliser($this->telephone),
            'whatsapp' => \App\Support\Telephone::pourWhatsapp($this->whatsappIdentique, $this->telephone, $this->whatsapp),
        ]);

        \App\Models\AuditLog::enregistrer(
            action: 'modification',
            entite: 'gestionnaire',
            entiteId: $gestionnaire->id,
            avant: $avant,
            apres: $gestionnaire->user->only(['nom', 'prenom', 'email', 'telephone']),
        );

        $this->gestionnaireEnEditionId = null;
        $this->reset(['nom', 'prenom', 'email', 'telephone']);
        session()->flash('succes_modification', __("Profil du gestionnaire mis à jour."));
    }

    public function basculerActif(int $gestionnaireId): void
    {
        $gestionnaire = Gestionnaire::with('user')->findOrFail($gestionnaireId);

        if ($this->refuserSiOrphelin($gestionnaire)) {
            return;
        }

        $nouveauStatut = ! $gestionnaire->actif;

        if (! $nouveauStatut) {
            $nbInvestisseursActifs = \App\Models\Investisseur::where('gestionnaire_id', $gestionnaire->id)
                ->where('statut', 'actif')
                ->count();

            if ($nbInvestisseursActifs > 0) {
                session()->flash('erreur_desactivation', __(
                    "Impossible de désactiver :nom :prenom : :nombre investisseur(s) actif(s) encore assigné(s). Réassignez-les tous d'un coup ci-dessous, ou un par un avec le bouton « Changer → » sur chaque fiche investisseur.",
                    [
                        'nom' => $gestionnaire->user->nom,
                        'prenom' => $gestionnaire->user->prenom ?? '',
                        'nombre' => $nbInvestisseursActifs,
                    ]
                ));
                $this->gestionnaireADesactiverId = $gestionnaireId;

                return;
            }
        }

        $gestionnaire->update(['actif' => $nouveauStatut]);
        $gestionnaire->user->update(['actif' => $nouveauStatut]);

        \App\Models\AuditLog::enregistrer(
            action: $nouveauStatut ? 'reactivation' : 'desactivation',
            entite: 'gestionnaire',
            entiteId: $gestionnaire->id,
            apres: ['email' => $gestionnaire->user->email, 'actif' => $nouveauStatut],
        );

        $this->gestionnaireADesactiverId = null;
    }

    public function annulerReassignationMasse(): void
    {
        $this->gestionnaireADesactiverId = null;
        $this->reset(['nouveauGestionnairePourReassignation', 'motifReassignationMasse']);
    }

    /**
     * Réassigne en une seule action tous les investisseurs actifs du gestionnaire à
     * désactiver vers un autre gestionnaire, puis termine la désactivation. Chaque
     * transfert passe par Investisseur::transfererVers() pour garder une trace
     * individuelle dans historique_affectations (même mécanisme que le transfert
     * unitaire depuis la fiche investisseur).
     */
    public function reassignerPortefeuilleEtDesactiver(): void
    {
        $ancienGestionnaire = Gestionnaire::with('user')->findOrFail($this->gestionnaireADesactiverId);

        $this->validate([
            'nouveauGestionnairePourReassignation' => 'required|exists:gestionnaires,id',
        ]);

        if ($this->nouveauGestionnairePourReassignation === $ancienGestionnaire->id) {
            $this->addError('nouveauGestionnairePourReassignation', __("Choisissez un gestionnaire différent de celui à désactiver."));

            return;
        }

        $nouveauGestionnaire = Gestionnaire::with('user')->findOrFail($this->nouveauGestionnairePourReassignation);

        // Le nouveau titulaire, lui, doit pouvoir se connecter : on ne transfere
        // pas un portefeuille vers une fiche sans compte.
        if ($nouveauGestionnaire->estOrphelin()) {
            $this->addError('nouveauGestionnairePourReassignation', __("Ce gestionnaire n'a plus de compte de connexion : choisissez-en un autre."));

            return;
        }

        $investisseurs = \App\Models\Investisseur::where('gestionnaire_id', $ancienGestionnaire->id)
            ->where('statut', 'actif')
            ->get();

        foreach ($investisseurs as $investisseur) {
            $investisseur->transfererVers($nouveauGestionnaire, $this->motifReassignationMasse ?: "Réassignation en masse — désactivation de {$ancienGestionnaire->nomComplet()}", \Illuminate\Support\Facades\Auth::id());
        }

        \App\Models\AuditLog::enregistrer(
            action: 'reassignation_masse',
            entite: 'gestionnaire',
            entiteId: $ancienGestionnaire->id,
            avant: ['gestionnaire' => $ancienGestionnaire->nomComplet()],
            apres: [
                'gestionnaire' => $nouveauGestionnaire->nomComplet(),
                'nombre_investisseurs' => $investisseurs->count(),
                'motif' => $this->motifReassignationMasse ?: null,
            ],
        );

        $ancienGestionnaire->update(['actif' => false]);
        $ancienGestionnaire->user?->update(['actif' => false]);

        \App\Models\AuditLog::enregistrer(
            action: 'desactivation',
            entite: 'gestionnaire',
            entiteId: $ancienGestionnaire->id,
            apres: ['email' => $ancienGestionnaire->user?->email, 'actif' => false],
        );

        $this->gestionnaireADesactiverId = null;
        $this->reset(['nouveauGestionnairePourReassignation', 'motifReassignationMasse']);
        session()->flash('succes_modification', __(
            ":nombre investisseur(s) réassigné(s) à :nouveau. :ancien a été désactivé.",
            [
                'nombre' => $investisseurs->count(),
                'nouveau' => $nouveauGestionnaire->nomComplet(),
                'ancien' => $ancienGestionnaire->nomComplet(),
            ]
        ));
    }

    /**
     * Réinitialise le mot de passe d'un gestionnaire (indispensable en l'absence d'envoi
     * d'email fonctionnel — voir la discussion). Un nouveau mot de passe temporaire est
     * généré et le changement sera exigé à la prochaine connexion.
     */
    public function reinitialiserMotDePasse(int $gestionnaireId): void
    {
        $gestionnaire = Gestionnaire::with('user')->findOrFail($gestionnaireId);

        if ($this->refuserSiOrphelin($gestionnaire)) {
            return;
        }

        $nouveauMotDePasse = \App\Support\MotDePasseTemporaire::generer();

        $gestionnaire->user->update([
            'password' => Hash::make($nouveauMotDePasse),
            'doit_changer_mot_de_passe' => true,
        ]);

        $this->dernierMotDePasseGenere = $nouveauMotDePasse;
        $this->emailConcerneParReinit = $gestionnaire->user->email;

        \Illuminate\Support\Facades\Mail::to($gestionnaire->user->email)->send(
            new \App\Mail\MotDePasseTemporaireMail($gestionnaire->user->nom . ' ' . $gestionnaire->user->prenom, $gestionnaire->user->email, $nouveauMotDePasse, estNouveauCompte: false)
        );

        \App\Models\AuditLog::enregistrer(
            action: 'reinitialisation_mdp',
            entite: 'gestionnaire',
            entiteId: $gestionnaire->id,
            apres: ['email' => $gestionnaire->user->email],
        );
    }
}
