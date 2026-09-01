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
        abort_if(Auth::user()->role === 'lecture', 403, 'Action non autorisée pour le rôle Lecture.');
    }

    /**
     * Le bouton est déjà masqué dans la vue pour un investisseur décédé, mais ces méthodes
     * ne passent pas par le middleware de route — vérification nécessaire ici aussi.
     */
    protected function interdireSiDecede(): void
    {
        abort_if($this->investisseur->estDecede(), 403, 'Ce compte est gelé — l\'investisseur est déclaré décédé.');
    }

    /**
     * Transfert de portefeuille et désactivation d'un dossier investisseur sont des
     * décisions administratives, réservées à la Direction/Administrateur — comme la
     * déclaration de décès ou la gestion de succession sur cette même page.
     */
    protected function interdireSiPasDirectionAdministrateur(): void
    {
        abort_if(! in_array(Auth::user()->role, ['direction', 'administrateur'], true), 403, 'Action réservée à la Direction/Administrateur.');
    }

    public function creerAcces(): void
    {
        $this->interdireSiLectureSeule();
        $this->interdireSiDecede();

        if ($this->investisseur->user_id) {
            return;
        }

        if (! $this->investisseur->email) {
            session()->flash('erreur_acces', 'Un email doit être renseigné sur le dossier avant de créer un accès (voir "Modifier le dossier").');
            return;
        }

        if (User::where('email', $this->investisseur->email)->exists()) {
            session()->flash('erreur_acces', 'Cet email est déjà utilisé par un autre compte utilisateur.');
            return;
        }

        $motDePasse = str()->random(10);

        $user = User::create([
            'nom' => $this->investisseur->nom,
            'prenom' => $this->investisseur->prenom,
            'email' => $this->investisseur->email,
            'telephone' => $this->investisseur->telephone,
            'password' => Hash::make($motDePasse),
            'role' => 'investisseur',
            'actif' => true,
            'doit_changer_mot_de_passe' => true,
        ]);

        $this->investisseur->update(['user_id' => $user->id]);

        $this->dernierMotDePasseGenere = $motDePasse;

        \Illuminate\Support\Facades\Mail::to($this->investisseur->email)->send(
            new \App\Mail\MotDePasseTemporaireMail($this->investisseur->nom . ' ' . $this->investisseur->prenom, $this->investisseur->email, $motDePasse, estNouveauCompte: true)
        );

        \App\Models\AuditLog::enregistrer(
            action: 'creation_acces_portail',
            entite: 'investisseur',
            entiteId: $this->investisseur->id,
            apres: ['email' => $this->investisseur->email],
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

        if (! $this->investisseur->user_id) {
            return;
        }

        $nouveauMotDePasse = str()->random(10);

        $this->investisseur->user->update([
            'password' => Hash::make($nouveauMotDePasse),
            'doit_changer_mot_de_passe' => true,
        ]);

        $this->dernierMotDePasseGenere = $nouveauMotDePasse;

        \Illuminate\Support\Facades\Mail::to($this->investisseur->email)->send(
            new \App\Mail\MotDePasseTemporaireMail($this->investisseur->nom . ' ' . $this->investisseur->prenom, $this->investisseur->email, $nouveauMotDePasse, estNouveauCompte: false)
        );

        \App\Models\AuditLog::enregistrer(
            action: 'reinitialisation_mdp',
            entite: 'investisseur',
            entiteId: $this->investisseur->id,
            apres: ['email' => $this->investisseur->email],
        );
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
            $this->addError('nouveauGestionnaireId', 'Cet investisseur est déjà assigné à ce gestionnaire.');
            return;
        }

        $this->investisseur->transfererVers($nouveauGestionnaire, $this->motifTransfert ?: null, Auth::id());

        \App\Models\AuditLog::enregistrer(
            action: 'transfert_gestionnaire',
            entite: 'investisseur',
            entiteId: $this->investisseur->id,
            avant: ['gestionnaire' => $ancienGestionnaire ? "{$ancienGestionnaire->user->nom} {$ancienGestionnaire->user->prenom}" : null],
            apres: ['gestionnaire' => "{$nouveauGestionnaire->user->nom} {$nouveauGestionnaire->user->prenom}", 'motif' => $this->motifTransfert ?: null],
        );

        $this->investisseur->refresh();
        $this->reset(['afficherFormulaireTransfert', 'nouveauGestionnaireId', 'motifTransfert']);
        session()->flash('succes', 'Gestionnaire réassigné avec succès.');
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
            'gestionnaires' => in_array(Auth::user()->role, ['direction', 'administrateur'], true)
                ? Gestionnaire::with('user')->where('actif', true)->get()
                : collect(),
            'historiqueAffectations' => in_array(Auth::user()->role, ['direction', 'administrateur'], true)
                ? $this->investisseur->historiqueAffectations()->with(['ancienGestionnaire.user', 'nouveauGestionnaire.user', 'effectuePar'])->latest('id')->get()
                : collect(),
        ]);
    }
}
