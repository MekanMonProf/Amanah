<?php

namespace App\Livewire\Gestionnaires;

use App\Livewire\Gestionnaires\Concerns\AgitSurUnGestionnaire;
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
    use AgitSurUnGestionnaire;

    use WithPagination;

    public bool $afficherFormulaire = false;

    #[Validate('required|string|min:8')]
    public string $mot_de_passe = '';

    public function ouvrirFormulaire(): void
    {
        $this->mot_de_passe = \App\Support\MotDePasseTemporaire::generer();
        $this->afficherFormulaire = true;
        $this->gestionnaireEnEditionId = null;
    }

    /** Les champs sont communs aux deux formulaires : un seul reste ouvert. */
    protected function fermerLeFormulaireDeCreation(): void
    {
        $this->afficherFormulaire = false;
    }

    public function creer(): void
    {
        $this->validate();

        if (\App\Support\Telephone::estAmbigu($this->telephone)) {
            $this->addError('telephone', __("Ce numéro semble étranger : précisez l'indicatif pays devant (ex : +33 pour la France, +221 pour le Sénégal)."));
            return;
        }

        $user = User::create([
            'nom' => $this->nom,
            'prenom' => $this->prenom,
            'email' => $this->email,
            'telephone' => \App\Support\Telephone::normaliser($this->telephone),
            'whatsapp' => \App\Support\Telephone::pourWhatsapp($this->whatsappIdentique, $this->telephone, $this->whatsapp),
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

        $this->reset(['nom', 'prenom', 'email', 'telephone', 'whatsapp', 'whatsappIdentique', 'mot_de_passe', 'afficherFormulaire']);
        $this->dispatch('gestionnaire-cree');
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
