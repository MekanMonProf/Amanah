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
    <div class="mb-4 text-sm text-gray-600">
        {{ __("Mot de passe oublié ? Pas de problème. Indiquez votre adresse email et nous vous enverrons un lien pour en choisir un nouveau.") }}
    </div>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form wire:submit="sendPasswordResetLink">
        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input wire:model="email" id="email" class="block mt-1 w-full" type="email" name="email" required autofocus />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div class="flex items-center justify-end mt-4">
            <x-primary-button>
                {{ __("Envoyer le lien de réinitialisation") }}
            </x-primary-button>
        </div>
    </form>
</div>
