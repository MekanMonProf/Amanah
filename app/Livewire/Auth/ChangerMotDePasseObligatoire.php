<?php

namespace App\Livewire\Auth;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('layouts.guest')]
class ChangerMotDePasseObligatoire extends Component
{
    #[Validate('required|current_password')]
    public string $mot_de_passe_actuel = '';

    #[Validate('required|confirmed')]
    public string $mot_de_passe = '';

    public string $mot_de_passe_confirmation = '';

    public function enregistrer(): void
    {
        $this->validate([
            'mot_de_passe_actuel' => ['required', 'current_password'],
            'mot_de_passe' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = Auth::user();
        $user->update([
            'password' => Hash::make($this->mot_de_passe),
            'doit_changer_mot_de_passe' => false,
        ]);

        $this->redirectRoute('dashboard', navigate: true);
    }

    public function render()
    {
        return view('livewire.auth.changer-mot-de-passe-obligatoire');
    }
}
