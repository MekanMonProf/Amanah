<?php

use Illuminate\Support\Facades\Password;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component {
    public string $email = '';

    public function sendPasswordResetLink(): void
    {
        $this->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        Password::sendResetLink($this->only('email'));

        session()->flash('status', 'Si cette adresse correspond à un compte, un lien de réinitialisation vient de vous être envoyé par email.');
    }
}; ?>

<div>
    <x-surtitre>{{ __("Mot de passe") }}</x-surtitre>
    <h1 class="mt-1 text-2xl font-bold text-gray-900">{{ __("Mot de passe oublié") }}</h1>
    {{-- La réinitialisation passe par l'email, et la plupart des investisseurs
         n'en ont pas : le dire ici évite d'attendre un message qui ne viendra
         jamais. --}}
    <p class="mt-1 text-sm text-gray-500">
        {{ __("Indiquez votre adresse email et nous vous enverrons un lien. Si votre dossier n'a pas d'email, demandez à votre gestionnaire de réinitialiser votre accès.") }}
    </p>

    <x-auth-session-status class="mt-6" :status="session('status')" />

    <form wire:submit="sendPasswordResetLink" class="mt-6">
        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input wire:model="email" id="email" class="block mt-1 w-full" type="email" name="email" required autofocus />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <x-primary-button class="mt-6 w-full justify-center">
            {{ __("Envoyer le lien de réinitialisation") }}
        </x-primary-button>
    </form>
</div>
