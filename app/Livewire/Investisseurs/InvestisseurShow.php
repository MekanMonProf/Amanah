<?php

namespace App\Livewire\Investisseurs;

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
        ]);
    }
}
