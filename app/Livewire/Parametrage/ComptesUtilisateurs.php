<?php

namespace App\Livewire\Parametrage;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\Droits;
use App\Support\Langue;
use App\Support\Modules;
use App\Support\MotDePasseTemporaire;
use App\Support\Telephone;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Tous les comptes de connexion, quel que soit leur rôle.
 *
 * Deux écrans créaient déjà des comptes — celui des gestionnaires, et la fiche
 * investisseur pour le portail — chacun avec des règles que cette liste n'a pas
 * à réinventer : un gestionnaire ne se désactive pas tant que son portefeuille
 * n'est pas réassigné, un accès investisseur se révoque depuis la fiche. Cet
 * écran-ci ne les double pas ; il donne la vue d'ensemble qui manquait, et il
 * crée les comptes qui n'avaient jusqu'ici aucun endroit où naître : direction,
 * administrateur, lecture.
 */
class ComptesUtilisateurs extends Component
{
    /** Les rôles qui ne dépendent d'aucune fiche, donc administrables ici. */
    public const ROLES_ADMINISTRABLES = ['direction', 'administrateur', 'lecture'];

    public string $recherche = '';

    public string $filtreRole = '';

    public bool $afficherFormulaire = false;

    public string $nom = '';

    public string $prenom = '';

    public string $email = '';

    public string $telephone = '';

    public string $role = 'lecture';

    public string $langue = Langue::DEFAUT;

    public ?string $dernierMotDePasseGenere = null;

    public ?int $comptePourMotDePasse = null;

    protected function rules(): array
    {
        return [
            'nom' => ['required', 'string', 'max:150'],
            'prenom' => ['nullable', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'telephone' => ['nullable', 'string', 'max:30'],
            'role' => ['required', Rule::in(self::ROLES_ADMINISTRABLES)],
            'langue' => ['required', Rule::in(array_keys(Langue::DISPONIBLES))],
        ];
    }

    public function ouvrirFormulaire(): void
    {
        Droits::exiger(Modules::MODULE_VERROU, Modules::ECRITURE);

        $this->reset(['nom', 'prenom', 'email', 'telephone', 'dernierMotDePasseGenere', 'comptePourMotDePasse']);
        $this->role = 'lecture';
        $this->langue = Langue::DEFAUT;
        $this->afficherFormulaire = true;
    }

    public function annuler(): void
    {
        $this->reset(['nom', 'prenom', 'email', 'telephone', 'afficherFormulaire']);
        $this->resetValidation();
    }

    public function creer(): void
    {
        Droits::exiger(Modules::MODULE_VERROU, Modules::ECRITURE);

        $this->validate();

        if (Telephone::estAmbigu($this->telephone)) {
            $this->addError('telephone', __("Ce numéro semble étranger : précisez l'indicatif pays devant (ex : +33 pour la France, +221 pour le Sénégal)."));

            return;
        }

        $motDePasse = MotDePasseTemporaire::generer(strtoupper(substr($this->role, 0, 3)));

        $utilisateur = User::create([
            'nom' => $this->nom,
            'prenom' => $this->prenom,
            'email' => $this->email,
            'telephone' => Telephone::normaliser($this->telephone),
            'whatsapp' => Telephone::normaliser($this->telephone),
            'password' => Hash::make($motDePasse),
            'role' => $this->role,
            'langue' => $this->langue,
            'actif' => true,
            'doit_changer_mot_de_passe' => true,
        ]);

        $this->dernierMotDePasseGenere = $motDePasse;
        $this->comptePourMotDePasse = $utilisateur->id;

        AuditLog::enregistrer(
            action: 'creation',
            entite: 'utilisateur',
            entiteId: $utilisateur->id,
            apres: ['email' => $utilisateur->email, 'role' => $utilisateur->role],
        );

        $this->reset(['nom', 'prenom', 'email', 'telephone', 'afficherFormulaire']);
    }

    /**
     * Le rôle ne se change qu'entre rôles administrables.
     *
     * Passer un gestionnaire à administrateur laisserait sa fiche `gestionnaires`
     * et son portefeuille derrière lui ; passer un investisseur à autre chose
     * couperait son portail de son dossier. Ces deux rôles se donnent et se
     * retirent là où vit la fiche correspondante, pas ici.
     */
    public function changerRole(int $utilisateurId, string $nouveauRole): void
    {
        Droits::exiger(Modules::MODULE_VERROU, Modules::ECRITURE);

        $utilisateur = User::findOrFail($utilisateurId);

        if (! $this->roleModifiable($utilisateur) || ! in_array($nouveauRole, self::ROLES_ADMINISTRABLES, true)) {
            session()->flash('erreur_comptes', __("Ce rôle se règle depuis l'écran qui gère la fiche correspondante."));

            return;
        }

        if ($utilisateur->role === $nouveauRole) {
            return;
        }

        if ($this->retireraitLeDernierAdministrateur($utilisateur, $nouveauRole !== 'administrateur')) {
            return;
        }

        $ancien = $utilisateur->role;
        $utilisateur->update(['role' => $nouveauRole]);

        AuditLog::enregistrer(
            action: 'modification',
            entite: 'utilisateur',
            entiteId: $utilisateur->id,
            avant: ['role' => $ancien],
            apres: ['role' => $nouveauRole],
        );

        session()->flash('succes_comptes', __("Rôle de :nom modifié.", ['nom' => $utilisateur->nom]));
    }

    public function basculerActif(int $utilisateurId): void
    {
        Droits::exiger(Modules::MODULE_VERROU, Modules::ECRITURE);

        $utilisateur = User::findOrFail($utilisateurId);

        if ($utilisateur->id === Auth::id()) {
            session()->flash('erreur_comptes', __("Vous ne pouvez pas désactiver votre propre compte."));

            return;
        }

        if ($utilisateur->role === 'gestionnaire') {
            session()->flash('erreur_comptes', __("La désactivation d'un gestionnaire passe par l'écran Gestionnaires : son portefeuille doit d'abord être réassigné."));

            return;
        }

        if ($utilisateur->actif && $this->retireraitLeDernierAdministrateur($utilisateur, true)) {
            return;
        }

        $utilisateur->update(['actif' => ! $utilisateur->actif]);

        AuditLog::enregistrer(
            action: $utilisateur->actif ? 'reactivation' : 'desactivation',
            entite: 'utilisateur',
            entiteId: $utilisateur->id,
            apres: ['email' => $utilisateur->email ?? $utilisateur->telephone, 'actif' => $utilisateur->actif],
        );

        session()->flash('succes_comptes', $utilisateur->actif
            ? __("Compte de :nom réactivé.", ['nom' => $utilisateur->nom])
            : __("Compte de :nom désactivé.", ['nom' => $utilisateur->nom]));
    }

    public function reinitialiserMotDePasse(int $utilisateurId): void
    {
        Droits::exiger(Modules::MODULE_VERROU, Modules::ECRITURE);

        $utilisateur = User::findOrFail($utilisateurId);

        $motDePasse = MotDePasseTemporaire::generer(strtoupper(substr($utilisateur->role, 0, 3)));

        $utilisateur->update([
            'password' => Hash::make($motDePasse),
            'doit_changer_mot_de_passe' => true,
        ]);

        $this->dernierMotDePasseGenere = $motDePasse;
        $this->comptePourMotDePasse = $utilisateur->id;

        AuditLog::enregistrer(
            action: 'reinitialisation_mdp',
            entite: 'utilisateur',
            entiteId: $utilisateur->id,
            apres: ['email' => $utilisateur->email ?? $utilisateur->telephone],
        );
    }

    /**
     * Refuse de laisser l'application sans aucun administrateur actif.
     *
     * Le verrou du paramétrage garde la porte ouverte à l'administrateur ; encore
     * faut-il qu'il en reste un pour la franchir.
     */
    private function retireraitLeDernierAdministrateur(User $utilisateur, bool $leRetire): bool
    {
        if (! $leRetire || $utilisateur->role !== 'administrateur' || ! $utilisateur->actif) {
            return false;
        }

        $restants = User::where('role', 'administrateur')
            ->where('actif', true)
            ->where('id', '!=', $utilisateur->id)
            ->count();

        if ($restants > 0) {
            return false;
        }

        session()->flash('erreur_comptes', __("C'est le dernier administrateur actif : nommez-en un autre avant de retirer celui-ci."));

        return true;
    }

    public function roleModifiable(User $utilisateur): bool
    {
        return in_array($utilisateur->role, self::ROLES_ADMINISTRABLES, true);
    }

    /** Où va-t-on pour administrer un compte qui ne se règle pas ici ? */
    public function rattachement(User $utilisateur): ?array
    {
        if ($utilisateur->role === 'gestionnaire') {
            return ['libelle' => __('Écran Gestionnaires'), 'route' => route('gestionnaires.index')];
        }

        $investisseur = \App\Models\Investisseur::where('user_id', $utilisateur->id)->first();

        return $investisseur
            ? ['libelle' => __('Fiche :identifiant', ['identifiant' => $investisseur->identifiant_externe]),
                'route' => route('investisseurs.show', $investisseur)]
            : null;
    }

    public function render()
    {
        $requete = User::query()->orderByRaw("FIELD(role, 'administrateur', 'direction', 'gestionnaire', 'lecture', 'investisseur')")
            ->orderBy('nom');

        if ($this->filtreRole !== '') {
            $requete->where('role', $this->filtreRole);
        }

        if (trim($this->recherche) !== '') {
            $terme = '%' . trim($this->recherche) . '%';
            $requete->where(fn ($q) => $q->where('nom', 'like', $terme)
                ->orWhere('prenom', 'like', $terme)
                ->orWhere('email', 'like', $terme)
                ->orWhere('telephone', 'like', $terme));
        }

        return view('livewire.parametrage.comptes-utilisateurs', [
            'comptes' => $requete->get(),
            'rolesAdministrables' => self::ROLES_ADMINISTRABLES,
            'effectifs' => User::selectRaw('role, count(*) n')->groupBy('role')->pluck('n', 'role'),
        ]);
    }
}
