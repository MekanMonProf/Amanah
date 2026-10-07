<?php

use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    /**
     * Send an email verification notification to the user.
     */
    public function sendVerification(): void
    {
        if (Auth::user()->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);

            return;
        }

        Auth::user()->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
    }

    /**
     * Log the current user out of the application.
     */
    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect('/', navigate: true);
    }
}; ?>

<div>
    {{-- Les textes de cet ecran etaient restes en anglais, herites du gabarit
         de depart. Celui d'origine parlait d'inscription ; ici personne ne
         s'inscrit, c'est un gestionnaire qui ouvre les comptes. --}}
    <x-surtitre>{{ __("Compte") }}</x-surtitre>
    <h1 class="mt-1 text-2xl font-bold text-gray-900">{{ __("Vérifiez votre email") }}</h1>
    <p class="mt-1 text-sm text-gray-500">
        {{ __("Un lien de vérification vient de vous être envoyé. Ouvrez-le pour confirmer votre adresse.") }}
    </p>

    @if (session('status') == 'verification-link-sent')
        <div class="mt-6 rounded-champ border border-primaire-200 bg-primaire-50 px-4 py-3 text-sm text-primaire-700">
            {{ __("Un nouveau lien de vérification vient d'être envoyé à votre adresse.") }}
        </div>
    @endif

    <x-primary-button wire:click="sendVerification" class="mt-6 w-full justify-center">
        {{ __("Renvoyer le lien de vérification") }}
    </x-primary-button>

    <div class="mt-4 text-center">
        <button wire:click="logout" type="button" class="text-sm text-gray-500 hover:text-gray-700 hover:underline">
            {{ __("Se déconnecter") }}
        </button>
    </div>
</div>
