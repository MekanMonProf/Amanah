<?php

namespace App\Livewire\Investisseurs;

use App\Models\Gestionnaire;
use App\Models\Investisseur;
use App\Models\User;
use App\Support\RestreintAuPortefeuilleGestionnaire;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class InvestisseurShow extends Component
{
    use RestreintAuPortefeuilleGestionnaire;

    public Investisseur $investisseur;

    public ?string $dernierMotDePasseGenere = null;

    public bool $afficherFormulaireTransfert = false;
    public ?int $nouveauGestionnaireId = null;
    public string $motifTransfert = '';

    public function mount(Investisseur $investisseur): void
    {
        $this->assurerAccesGestionnaire($investisseur);
        $this->investisseur = $investisseur;
    }

    /**
     * La page reste consultable par le rôle Lecture, mais ses actions d'écriture
     * (création/réinitialisation d'accès) doivent rester interdites — vérification
     * nécessaire ici car ces méthodes ne passent pas par le middleware de route.
     */
    protected function interdireSiLectureSeule(): void
    {
        abort_if(Auth::user()->role === 'lecture', 403, __("Action non autorisée pour le rôle Lecture."));
    }

    /**
     * Le bouton est déjà masqué dans la vue pour un investisseur décédé, mais ces méthodes
     * ne passent pas par le middleware de route — vérification nécessaire ici aussi.
     */
    protected function interdireSiDecede(): void
    {
        abort_if($this->investisseur->estDecede(), 403, __("Ce compte est gelé — l'investisseur est déclaré décédé."));
    }

    /**
     * Transfert de portefeuille et désactivation d'un dossier investisseur sont des
     * décisions administratives, réservées à la Direction/Administrateur — comme la
     * déclaration de décès ou la gestion de succession sur cette même page.
     */
    protected function interdireSiPasDirectionAdministrateur(): void
    {
        abort_if(! in_array(Auth::user()->role, ['direction', 'administrateur'], true), 403, __("Action réservée à la Direction/Administrateur."));
    }

    /**
     * La plupart des investisseurs n'ont pas d'email (téléphone quasi universel, email
     * minoritaire) — l'accès portail utilise l'email s'il existe (comportement inchangé),
     * sinon le téléphone comme identifiant de connexion. Le mot de passe reste affiché à
     * l'écran dans les deux cas ; sans email il n'y a simplement personne à qui l'envoyer,
     * le gestionnaire le transmet directement.
     */
    public function creerAcces(): void
    {
        $this->interdireSiLectureSeule();
        $this->interdireSiDecede();

        if ($this->investisseur->user_id) {
            return;
        }

        $aUnEmail = (bool) $this->investisseur->email;
        // Normalisé même si InvestisseurEdit l'a déjà fait à la saisie : couvre aussi les
        // dossiers plus anciens jamais repassés par le formulaire depuis (voir App\Support\Telephone).
        $telephoneNormalise = \App\Support\Telephone::normaliser($this->investisseur->telephone);

        if (! $aUnEmail && $telephoneNormalise === null) {
            session()->flash('erreur_acces', __('Un email ou un numéro de téléphone doit être renseigné sur le dossier avant de créer un accès (voir « Modifier le dossier »).'));
            return;
        }

        if ($aUnEmail && User::where('email', $this->investisseur->email)->exists()) {
            session()->flash('erreur_acces', __("Cet email est déjà utilisé par un autre compte utilisateur."));
            return;
        }

        if (! $aUnEmail && User::where('telephone', $telephoneNormalise)->exists()) {
            session()->flash('erreur_acces', __("Ce numéro de téléphone est déjà utilisé par un autre compte utilisateur."));
            return;
        }

        // Préfixé par l'identifiant de l'investisseur : le gestionnaire n'a plus que le
        // bloc aléatoire à lui dicter (voir App\Support\MotDePasseTemporaire).
        $motDePasse = \App\Support\MotDePasseTemporaire::generer($this->investisseur->identifiant_externe);

        $user = User::create([
            'nom' => $this->investisseur->nom,
            // users.prenom est NOT NULL en base, contrairement à investisseurs.prenom —
            // un investisseur "morale" (entreprise) n'a souvent pas de prénom renseigné.
            'prenom' => $this->investisseur->prenom ?? '',
            // Le téléphone n'est stocké ici que lorsqu'il sert réellement d'identifiant
            // de connexion (pas d'email) — sinon deux comptes email pourraient partager
            // le même téléphone de famille et se heurter à la contrainte d'unicité.
            'email' => $aUnEmail ? $this->investisseur->email : null,
            'telephone' => $aUnEmail ? null : $telephoneNormalise,
            'password' => Hash::make($motDePasse),
            'role' => 'investisseur',
            'actif' => true,
            'doit_changer_mot_de_passe' => true,
        ]);

        $this->investisseur->update(['user_id' => $user->id]);

        $this->dernierMotDePasseGenere = $motDePasse;

        if ($aUnEmail) {
            \Illuminate\Support\Facades\Mail::to($this->investisseur->email)->send(
                new \App\Mail\MotDePasseTemporaireMail($this->investisseur->nom . ' ' . $this->investisseur->prenom, $this->investisseur->email, $motDePasse, estNouveauCompte: true)
            );
        }

        \App\Models\AuditLog::enregistrer(
            action: 'creation_acces_portail',
            entite: 'investisseur',
            entiteId: $this->investisseur->id,
            apres: [
                'identifiant_connexion' => $aUnEmail ? 'email' : 'telephone',
                'email' => $this->investisseur->email,
                'telephone' => $this->investisseur->telephone,
            ],
        );
    }

    /**
     * Réinitialise le mot de passe de l'accès portail de l'investisseur — indispensable
     * en l'absence d'envoi d'email fonctionnel (voir la discussion sur ce point).
     */
    public function reinitialiserMotDePasse(): void
    {
        $this->interdireSiLectureSeule();
        $this->interdireSiDecede();

        // Un accès révoqué ne se réinitialise pas : il se rétablit d'abord. Sans
        // cela on remettrait un mot de passe à un compte qui ne peut pas se connecter,
        // et le gestionnaire croirait l'avoir réouvert.
        if (! $this->investisseur->user_id || ! $this->investisseur->user->actif) {
            return;
        }

        $nouveauMotDePasse = \App\Support\MotDePasseTemporaire::generer($this->investisseur->identifiant_externe);

        $this->investisseur->user->update([
            'password' => Hash::make($nouveauMotDePasse),
            'doit_changer_mot_de_passe' => true,
        ]);

        $this->dernierMotDePasseGenere = $nouveauMotDePasse;

        if ($this->investisseur->user->email) {
            \Illuminate\Support\Facades\Mail::to($this->investisseur->user->email)->send(
                new \App\Mail\MotDePasseTemporaireMail($this->investisseur->nom . ' ' . $this->investisseur->prenom, $this->investisseur->user->email, $nouveauMotDePasse, estNouveauCompte: false)
            );
        }

        \App\Models\AuditLog::enregistrer(
            action: 'reinitialisation_mdp',
            entite: 'investisseur',
            entiteId: $this->investisseur->id,
            apres: ['email' => $this->investisseur->user->email, 'telephone' => $this->investisseur->user->telephone],
        );
    }

    /**
     * Retire l'accès au portail sans supprimer le compte.
     *
     * Le compte utilisateur est désactivé, pas effacé. L'effacer romprait le journal
     * d'audit — audit_logs.user_id est en ON DELETE RESTRICT, et un investisseur y
     * figure dès qu'il a activé sa double authentification — et ferait perdre la
     * trace de qui avait accès. Désactivé, le compte ne peut plus se connecter, et
     * EnsureCompteActif coupe même une session déjà ouverte au prochain écran.
     *
     * Contrairement à la création et à la réinitialisation, la révocation reste
     * permise sur le dossier d'un défunt : c'est précisément le moment où l'accès
     * doit tomber, pour que personne ne se connecte en son nom pendant la succession.
     */
    public function revoquerAcces(): void
    {
        $this->interdireSiLectureSeule();

        $utilisateur = $this->investisseur->user;

        if (! $utilisateur || ! $utilisateur->actif) {
            return;
        }

        $utilisateur->update(['actif' => false]);

        $this->dernierMotDePasseGenere = null;

        \App\Models\AuditLog::enregistrer(
            action: 'revocation_acces_portail',
            entite: 'investisseur',
            entiteId: $this->investisseur->id,
            avant: ['actif' => true],
            apres: [
                'actif' => false,
                'identifiant_connexion' => $utilisateur->email ?? $utilisateur->telephone,
            ],
        );

        session()->flash('message_acces', __("L'accès au portail est révoqué. L'investisseur ne peut plus se connecter ; une session en cours est coupée immédiatement."));
    }

    /**
     * Rend un accès révoqué, sans toucher au mot de passe — comme la réactivation
     * d'un gestionnaire. Si les identifiants doivent changer, le bouton de
     * réinitialisation est juste à côté.
     */
    public function retablirAcces(): void
    {
        $this->interdireSiLectureSeule();
        $this->interdireSiDecede();

        $utilisateur = $this->investisseur->user;

        if (! $utilisateur || $utilisateur->actif) {
            return;
        }

        $utilisateur->update(['actif' => true]);

        \App\Models\AuditLog::enregistrer(
            action: 'retablissement_acces_portail',
            entite: 'investisseur',
            entiteId: $this->investisseur->id,
            avant: ['actif' => false],
            apres: [
                'actif' => true,
                'identifiant_connexion' => $utilisateur->email ?? $utilisateur->telephone,
            ],
        );

        session()->flash('message_acces', __("L'accès au portail est rétabli avec le même identifiant et le même mot de passe qu'avant."));
    }

    /**
     * Change le gestionnaire assigné à l'investisseur — trace l'ancien et le nouveau
     * dans historique_affectations via Investisseur::transfererVers().
     */
    public function transfererGestionnaire(): void
    {
        $this->interdireSiPasDirectionAdministrateur();
        $this->interdireSiDecede();

        $this->validate([
            'nouveauGestionnaireId' => 'required|exists:gestionnaires,id',
            'motifTransfert' => 'nullable|string|max:255',
        ]);

        $nouveauGestionnaire = Gestionnaire::findOrFail($this->nouveauGestionnaireId);
        $ancienGestionnaire = $this->investisseur->gestionnaire;

        if ($ancienGestionnaire && $ancienGestionnaire->id === $nouveauGestionnaire->id) {
            $this->addError('nouveauGestionnaireId', __("Cet investisseur est déjà assigné à ce gestionnaire."));
            return;
        }

        $this->investisseur->transfererVers($nouveauGestionnaire, $this->motifTransfert ?: null, Auth::id());

        \App\Models\AuditLog::enregistrer(
            action: 'transfert_gestionnaire',
            entite: 'investisseur',
            entiteId: $this->investisseur->id,
            avant: ['gestionnaire' => $ancienGestionnaire?->nomComplet()],
            apres: ['gestionnaire' => $nouveauGestionnaire->nomComplet(), 'motif' => $this->motifTransfert ?: null],
        );

        $this->investisseur->refresh();
        $this->reset(['afficherFormulaireTransfert', 'nouveauGestionnaireId', 'motifTransfert']);
        session()->flash('succes', __("Gestionnaire réassigné avec succès."));
    }

    /**
     * Désactive ou réactive le dossier investisseur. La désactivation coupe aussi
     * l'accès portail (comme pour un gestionnaire désactivé — voir EnsureCompteActif) :
     * sans ça, un investisseur "désactivé" pourrait quand même continuer à se connecter
     * à /mon-compte.
     */
    public function basculerActifInvestisseur(): void
    {
        $this->interdireSiPasDirectionAdministrateur();
        $this->interdireSiDecede();

        $nouveauStatut = $this->investisseur->statut === 'actif' ? 'inactif' : 'actif';
        $ancienStatut = $this->investisseur->statut;

        $this->investisseur->update(['statut' => $nouveauStatut]);

        if ($this->investisseur->user_id) {
            $this->investisseur->user->update(['actif' => $nouveauStatut === 'actif']);
        }

        \App\Models\AuditLog::enregistrer(
            action: $nouveauStatut === 'actif' ? 'reactivation' : 'desactivation',
            entite: 'investisseur',
            entiteId: $this->investisseur->id,
            avant: ['statut' => $ancienStatut],
            apres: ['statut' => $nouveauStatut],
        );

        $this->investisseur->refresh();
    }

    public function render()
    {
        $comptes = $this->investisseur->comptes()->with(['achats', 'ecritures'])->get();

        $comptesEnrichis = $comptes->map(function ($compte) {
            return [
                'compte' => $compte,
                'nombre_actions' => $compte->nombreActions(),
                'solde' => $compte->solde(),
                'achats' => $compte->achats()->latest('date_achat')->get(),
                'dernieres_ecritures' => $compte->ecritures()->reorder('id', 'desc')->take(10)->get(),
            ];
        });

        return view('livewire.investisseurs.investisseur-show', [
            'comptesEnrichis' => $comptesEnrichis,
            // Les actions offertes à la mémoire d'un défunt sont portées au compte du Waqf
            // caritatif : sans ce rappel, elles seraient invisibles sur la fiche du donateur.
            'presents' => \App\Models\AchatAction::where('offert_par_investisseur_id', $this->investisseur->id)
                ->latest('date_achat')->get(),
            // Et symétriquement, les presents faites en son honneur : à sa mémoire s'il est
            // décédé, à son profit s'il est vivant. Plus de filtre sur le statut — un vivant
            // peut recevoir un cadeau d'actions Waqf.
            'presentsRecus' => \App\Models\AchatAction::with('offertPar')
                ->where('present_pour_investisseur_id', $this->investisseur->id)
                ->latest('date_achat')->get(),
            'gestionnaires' => in_array(Auth::user()->role, ['direction', 'administrateur'], true)
                ? Gestionnaire::avecCompte()->with('user')->where('actif', true)->get()
                : collect(),
            'historiqueAffectations' => in_array(Auth::user()->role, ['direction', 'administrateur'], true)
                ? $this->investisseur->historiqueAffectations()->with(['ancienGestionnaire.user', 'nouveauGestionnaire.user', 'effectuePar'])->latest('id')->get()
                : collect(),
        ]);
    }
}
