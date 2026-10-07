<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public string $password = '';

    /**
     * Confirm the current user's password.
     */
    public function confirmPassword(): void
    {
        $this->validate([
            'password' => ['required', 'string'],
        ]);

        if (! Auth::guard('web')->validate([
            'email' => Auth::user()->email,
            'password' => $this->password,
        ])) {
            throw ValidationException::withMessages([
                'password' => __('auth.password'),
            ]);
        }

        session(['auth.password_confirmed_at' => time()]);

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div>
    {{-- Les textes de cet écran étaient restés en anglais, hérités du gabarit
         de départ, dans une application qui parle trois langues dont aucune
         n'est celle-là par défaut. --}}
    <x-surtitre>{{ __("Sécurité") }}</x-surtitre>
    <h1 class="mt-1 text-2xl font-bold text-gray-900">{{ __("Confirmez votre mot de passe") }}</h1>
    <p class="mt-1 text-sm text-gray-500">
        {{ __("Vous entrez dans une zone sensible de l'application.") }}
    </p>

    <form wire:submit="confirmPassword" class="mt-6">
        <div>
            <x-input-label for="password" :value="__('Mot de passe')" />
            <x-text-input wire:model="password" id="password" class="block mt-1 w-full"
                          type="password" name="password" required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <x-primary-button class="mt-6 w-full justify-center">
            {{ __("Confirmer") }}
        </x-primary-button>
    </form>
</div>
