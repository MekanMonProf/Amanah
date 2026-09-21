<div>
    <div class="mb-4 text-sm text-gray-600">
        Entrez le code à 6 chiffres de votre application d'authentification, ou l'un de vos codes de récupération.
    </div>

    <form wire:submit="verifier">
        <div>
            <x-input-label for="code" :value="__('Code de vérification')" />
            <x-text-input wire:model="code" id="code" class="block mt-1 w-full text-center text-lg tracking-widest" type="text" inputmode="numeric" autofocus autocomplete="one-time-code" />
            <x-input-error :messages="$errors->get('code')" class="mt-2" />
        </div>

        <div class="flex items-center justify-between mt-4">
            <button type="button" wire:click="deconnexion" class="text-sm text-gray-500 hover:text-gray-700 underline">
                {{ __("Se déconnecter") }}
            </button>

            <x-primary-button>{{ __("Vérifier") }}</x-primary-button>
        </div>
    </form>
</div>
