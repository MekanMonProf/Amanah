<?php

namespace App\Livewire\Gestionnaires;

use App\Models\Gestionnaire;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class GestionnaireIndex extends Component
{
    use WithPagination;

    public bool $afficherFormulaire = false;

    #[Validate('required|string|max:150')]
    public string $nom = '';

    #[Validate('nullable|string|max:150')]
    public string $prenom = '';

    #[Validate('required|email|max:190|unique:users,email')]
    public string $email = '';

    #[Validate('nullable|string|max:30')]
    public string $telephone = '';

    #[Validate('required|string|min:8')]
    public string $mot_de_passe = '';

    public ?string $dernierMotDePasseGenere = null;
    public ?string $emailConcerneParReinit = null;

    public ?int $gestionnaireEnEditionId = null;

    public ?int $gestionnaireADesactiverId = null;
    public ?int $nouveauGestionnairePourReassignation = null;
    public string $motifReassignationMasse = '';

    public function ouvrirFormulaire(): void
    {
        $this->mot_de_passe = \App\Support\MotDePasseTemporaire::generer();
        $this->afficherFormulaire = true;
        $this->gestionnaireEnEditionId = null;
    }

    /**
     * Ouvre le formulaire de création, pré-rempli avec les infos actuelles du gestionnaire —
     * réutilise les mêmes champs (nom/prenom/email/telephone) pour profiter des messages
     * de validation français déjà mappés pour ces noms de propriétés.
     */
    public function modifier(int $gestionnaireId): void
    {
        $gestionnaire = Gestionnaire::with('user')->findOrFail($gestionnaireId);

        $this->gestionnaireEnEditionId = $gestionnaireId;
        $this->nom = $gestionnaire->user->nom;
        $this->prenom = $gestionnaire->user->prenom ?? '';
        $this->email = $gestionnaire->user->email;
        $this->telephone = $gestionnaire->user->telephone ?? '';
        $this->afficherFormulaire = false;
        $this->resetErrorBag();
    }

    public function annulerModification(): void
    {
        $this->gestionnaireEnEditionId = null;
        $this->reset(['nom', 'prenom', 'email', 'telephone']);
    }

    public function enregistrerModification(): void
    {
        $gestionnaire = Gestionnaire::with('user')->findOrFail($this->gestionnaireEnEditionId);

        $this->validate([
            'nom' => 'required|string|max:150',
            'prenom' => 'nullable|string|max:150',
            'email' => 'required|email|max:190|unique:users,email,' . $gestionnaire->user_id,
            'telephone' => 'nullable|string|max:30',
        ]);

        if (\App\Support\Telephone::estAmbigu($this->telephone)) {
            $this->addError('telephone', 'Ce numéro semble étranger : précisez l\'indicatif pays devant (ex : +33 pour la France, +221 pour le Sénégal).');
            return;
        }

        $avant = $gestionnaire->user->only(['nom', 'prenom', 'email', 'telephone']);

        $gestionnaire->user->update([
            'nom' => $this->nom,
            'prenom' => $this->prenom ?: null,
            'email' => $this->email,
            'telephone' => \App\Support\Telephone::normaliser($this->telephone),
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
        session()->flash('succes_modification', 'Profil du gestionnaire mis à jour.');
    }

    public function creer(): void
    {
        $this->validate();

        if (\App\Support\Telephone::estAmbigu($this->telephone)) {
            $this->addError('telephone', 'Ce numéro semble étranger : précisez l\'indicatif pays devant (ex : +33 pour la France, +221 pour le Sénégal).');
            return;
        }

        $user = User::create([
            'nom' => $this->nom,
            'prenom' => $this->prenom,
            'email' => $this->email,
            'telephone' => \App\Support\Telephone::normaliser($this->telephone),
            'password' => Hash::make($this->mot_de_passe),
            'role' => 'gestionnaire',
            'actif' => true,
            'doit_changer_mot_de_passe' => true,
        ]);

        Gestionnaire::create(['user_id' => $user->id, 'actif' => true]);

        $this->dernierMotDePasseGenere = $this->mot_de_passe;
        $this->emailConcerneParReinit = null;

        \Illuminate\Support\Facades\Mail::to($user->email)->send(
            new \App\Mail\MotDePasseTemporaireMail($user->nom . ' ' . $user->prenom, $user->email, $this->mot_de_passe, estNouveauCompte: true)
        );

        \App\Models\AuditLog::enregistrer(
            action: 'creation',
            entite: 'gestionnaire',
            entiteId: $user->id,
            apres: ['nom' => $user->nom, 'prenom' => $user->prenom, 'email' => $user->email],
        );

        $this->reset(['nom', 'prenom', 'email', 'telephone', 'mot_de_passe', 'afficherFormulaire']);
        $this->dispatch('gestionnaire-cree');
    }

    /**
     * Un gestionnaire ne peut pas être désactivé tant qu'il a des investisseurs actifs
     * assignés — sinon son portefeuille se retrouve sans personne pour le gérer (le
     * gestionnaire désactivé ne peut plus se connecter). Pratique courante dans les
     * institutions financières : la désactivation d'un chargé de compte est bloquée
     * tant que son portefeuille n'a pas été réassigné.
     */
    public function basculerActif(int $gestionnaireId): void
    {
        $gestionnaire = Gestionnaire::findOrFail($gestionnaireId);
        $nouveauStatut = ! $gestionnaire->actif;

        if (! $nouveauStatut) {
            $nbInvestisseursActifs = \App\Models\Investisseur::where('gestionnaire_id', $gestionnaire->id)
                ->where('statut', 'actif')
                ->count();

            if ($nbInvestisseursActifs > 0) {
                session()->flash('erreur_desactivation', sprintf(
                    'Impossible de désactiver %s %s : %d investisseur(s) actif(s) encore assigné(s). Réassignez-les tous d\'un coup ci-dessous, ou un par un avec le bouton "Changer →" sur chaque fiche investisseur.',
                    $gestionnaire->user->nom,
                    $gestionnaire->user->prenom,
                    $nbInvestisseursActifs
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
            $this->addError('nouveauGestionnairePourReassignation', 'Choisissez un gestionnaire différent de celui à désactiver.');
            return;
        }

        $nouveauGestionnaire = Gestionnaire::with('user')->findOrFail($this->nouveauGestionnairePourReassignation);

        $investisseurs = \App\Models\Investisseur::where('gestionnaire_id', $ancienGestionnaire->id)
            ->where('statut', 'actif')
            ->get();

        foreach ($investisseurs as $investisseur) {
            $investisseur->transfererVers($nouveauGestionnaire, $this->motifReassignationMasse ?: "Réassignation en masse — désactivation de {$ancienGestionnaire->user->nom} {$ancienGestionnaire->user->prenom}", \Illuminate\Support\Facades\Auth::id());
        }

        \App\Models\AuditLog::enregistrer(
            action: 'reassignation_masse',
            entite: 'gestionnaire',
            entiteId: $ancienGestionnaire->id,
            avant: ['gestionnaire' => "{$ancienGestionnaire->user->nom} {$ancienGestionnaire->user->prenom}"],
            apres: [
                'gestionnaire' => "{$nouveauGestionnaire->user->nom} {$nouveauGestionnaire->user->prenom}",
                'nombre_investisseurs' => $investisseurs->count(),
                'motif' => $this->motifReassignationMasse ?: null,
            ],
        );

        $ancienGestionnaire->update(['actif' => false]);
        $ancienGestionnaire->user->update(['actif' => false]);

        \App\Models\AuditLog::enregistrer(
            action: 'desactivation',
            entite: 'gestionnaire',
            entiteId: $ancienGestionnaire->id,
            apres: ['email' => $ancienGestionnaire->user->email, 'actif' => false],
        );

        $this->gestionnaireADesactiverId = null;
        $this->reset(['nouveauGestionnairePourReassignation', 'motifReassignationMasse']);
        session()->flash('succes_modification', sprintf(
            '%d investisseur(s) réassigné(s) à %s %s. %s %s a été désactivé.',
            $investisseurs->count(),
            $nouveauGestionnaire->user->nom,
            $nouveauGestionnaire->user->prenom,
            $ancienGestionnaire->user->nom,
            $ancienGestionnaire->user->prenom,
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

    public function render()
    {
        return view('livewire.gestionnaires.gestionnaire-index', [
            'gestionnaires' => Gestionnaire::with('user')
                ->withCount('investisseurs')
                ->orderBy('id')
                ->paginate(20),
        ]);
    }
}
