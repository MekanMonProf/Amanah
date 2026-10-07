<div>
    <x-surtitre>{{ __("Sécurité") }}</x-surtitre>
    <h1 class="mt-1 text-2xl font-bold text-gray-900">{{ __("Vérification en deux étapes") }}</h1>
    <p class="mt-1 text-sm text-gray-500">
        {{ __("Entrez le code à 6 chiffres de votre application d'authentification, ou l'un de vos codes de récupération.") }}
    </p>

    <form wire:submit="verifier" class="mt-6">
        <div>
            <x-input-label for="code" :value="__('Code de vérification')" />
            <x-text-input wire:model="code" id="code" class="block mt-1 w-full text-center text-lg tracking-widest" type="text" inputmode="numeric" autofocus autocomplete="one-time-code" />
            <x-input-error :messages="$errors->get('code')" class="mt-2" />
        </div>

        <x-primary-button class="mt-6 w-full justify-center">{{ __("Vérifier") }}</x-primary-button>

        {{-- La sortie passe sous le bouton principal : c'est le recours, pas
             l'action qu'on vient faire ici. --}}
        <div class="mt-4 text-center">
            <button type="button" wire:click="deconnexion" class="text-sm text-gray-500 hover:text-gray-700 hover:underline">
                {{ __("Se déconnecter") }}
            </button>
        </div>
    </form>
</div>
