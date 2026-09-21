<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component {
    public string $password = '';

    public function deleteUser(\Livewire\Volt\Component $component): void
    {
        $this->validate([
            'password' => ['required', 'string', 'current_password'],
        ]);

        $user = Auth::user();

        Auth::logout();

        $user->delete();

        request()->session()->invalidate();
        request()->session()->regenerateToken();

        $this->redirect('/', navigate: true);
    }
}; ?>

<section class="space-y-6">
    <header>
        <h2 class="text-lg font-medium text-gray-900">
            {{ __("Supprimer le compte") }}
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            Une fois votre compte supprimé, toutes ses ressources et données seront définitivement effacées.
            Avant de supprimer votre compte, téléchargez toute donnée ou information que vous souhaitez conserver.
        </p>
    </header>

    <x-danger-button
        x-data=""
        x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')"
    >{{ __("Supprimer le compte") }}</x-danger-button>

    <x-modal name="confirm-user-deletion" :show="$errors->isNotEmpty()" focusable>
        <form wire:submit="deleteUser" class="p-6">
            <h2 class="text-lg font-medium text-gray-900">
                {{ __("Êtes-vous sûr de vouloir supprimer votre compte ?") }}
            </h2>

            <p class="mt-1 text-sm text-gray-600">
                Une fois votre compte supprimé, toutes ses ressources et données seront définitivement effacées.
                Merci d'entrer votre mot de passe pour confirmer que vous souhaitez supprimer définitivement votre compte.
            </p>

            <div class="mt-6">
                <x-input-label for="password" value="Mot de passe" class="sr-only" />

                <x-text-input
                    wire:model="password"
                    id="password"
                    name="password"
                    type="password"
                    class="mt-1 block w-3/4"
                    placeholder="Mot de passe"
                />

                <x-input-error :messages="$errors->get('password')" class="mt-2" />
            </div>

            <div class="mt-6 flex justify-end">
                <x-secondary-button x-on:click="$dispatch('close')">
                    {{ __("Annuler") }}
                </x-secondary-button>

                <x-danger-button class="ms-3">
                    {{ __("Supprimer le compte") }}
                </x-danger-button>
            </div>
        </form>
    </x-modal>
</section>
