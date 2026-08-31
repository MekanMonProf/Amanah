<?php

namespace App\Livewire\Investisseurs;

use App\Models\Investisseur;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class InvestisseurShow extends Component
{
    public Investisseur $investisseur;

    public ?string $dernierMotDePasseGenere = null;

    public function mount(Investisseur $investisseur): void
    {
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

    public function creerAcces(): void
    {
        $this->interdireSiLectureSeule();

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

        if (! $this->investisseur->user_id) {
            return;
        }

        $nouveauMotDePasse = str()->random(10);

        $this->investisseur->user->update([
            'password' => Hash::make($nouveauMotDePasse),
            'doit_changer_mot_de_passe' => true,
        ]);

        $this->dernierMotDePasseGenere = $nouveauMotDePasse;

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
