<div>
    <div class="mb-4 text-sm text-gray-600">
        Pour des raisons de sécurité, vous devez définir votre propre mot de passe avant de continuer.
    </div>

    <form wire:submit="enregistrer">
        <div>
            <x-input-label for="mot_de_passe_actuel" :value="__('Mot de passe temporaire')" />
            <x-text-input wire:model="mot_de_passe_actuel" id="mot_de_passe_actuel" class="block mt-1 w-full" type="password" required autofocus />
            <x-input-error :messages="$errors->get('mot_de_passe_actuel')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="mot_de_passe" :value="__('Nouveau mot de passe')" />
            <x-text-input wire:model="mot_de_passe" id="mot_de_passe" class="block mt-1 w-full" type="password" required />
            <x-input-error :messages="$errors->get('mot_de_passe')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="mot_de_passe_confirmation" :value="__('Confirmer le nouveau mot de passe')" />
            <x-text-input wire:model="mot_de_passe_confirmation" id="mot_de_passe_confirmation" class="block mt-1 w-full" type="password" required />
            <x-input-error :messages="$errors->get('mot_de_passe_confirmation')" class="mt-2" />
        </div>

        <div class="flex items-center justify-end mt-4">
            <x-primary-button>
                {{ __('Définir mon mot de passe') }}
            </x-primary-button>
        </div>
    </form>
</div>
