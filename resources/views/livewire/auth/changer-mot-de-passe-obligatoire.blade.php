<div>
    {{-- Le premier écran que traverse un nouvel investisseur, juste après avoir
         reçu ses identifiants. Il s'annonce donc comme la connexion dont il
         sort, et dit pourquoi il est là. --}}
    <x-surtitre>{{ __("Sécurité") }}</x-surtitre>
    <h1 class="mt-1 text-2xl font-bold text-gray-900">{{ __("Choisissez votre mot de passe") }}</h1>
    <p class="mt-1 text-sm text-gray-500">
        {{ __("Celui que vous avez reçu est temporaire et ne sert qu'une fois.") }}
    </p>

    <form wire:submit="enregistrer" class="mt-6">
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

        <x-primary-button class="mt-6 w-full justify-center">
            {{ __('Définir mon mot de passe') }}
        </x-primary-button>
    </form>
</div>
