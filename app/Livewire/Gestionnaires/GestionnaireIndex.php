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

    public function ouvrirFormulaire(): void
    {
        $this->mot_de_passe = str()->random(10);
        $this->afficherFormulaire = true;
    }

    public function creer(): void
    {
        $this->validate();

        $user = User::create([
            'nom' => $this->nom,
            'prenom' => $this->prenom,
            'email' => $this->email,
            'telephone' => $this->telephone ?: null,
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

    public function basculerActif(int $gestionnaireId): void
    {
        $gestionnaire = Gestionnaire::findOrFail($gestionnaireId);
        $nouveauStatut = ! $gestionnaire->actif;
        $gestionnaire->update(['actif' => $nouveauStatut]);
        $gestionnaire->user->update(['actif' => $nouveauStatut]);

        \App\Models\AuditLog::enregistrer(
            action: $nouveauStatut ? 'reactivation' : 'desactivation',
            entite: 'gestionnaire',
            entiteId: $gestionnaire->id,
            apres: ['email' => $gestionnaire->user->email, 'actif' => $nouveauStatut],
        );
    }

    /**
     * Réinitialise le mot de passe d'un gestionnaire (indispensable en l'absence d'envoi
     * d'email fonctionnel — voir la discussion). Un nouveau mot de passe temporaire est
     * généré et le changement sera exigé à la prochaine connexion.
     */
    public function reinitialiserMotDePasse(int $gestionnaireId): void
    {
        $gestionnaire = Gestionnaire::with('user')->findOrFail($gestionnaireId);

        $nouveauMotDePasse = str()->random(10);

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
