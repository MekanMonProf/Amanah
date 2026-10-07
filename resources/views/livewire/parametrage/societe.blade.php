<form wire:submit="enregistrer" class="bg-white border rounded-carte">
    <div class="p-5 border-b">
        <h2 class="font-semibold text-gray-800">{{ __("Informations de la société") }}</h2>
        <p class="mt-1 text-sm text-gray-500">
            {{ __("Ce que la maison dit d'elle-même : l'en-tête des documents PDF, les coordonnées données aux investisseurs, et ce que voit un membre de l'équipe qui demande de l'aide.") }}
        </p>
        <p class="mt-2 text-sm text-gray-500">
            {{ __("Un champ laissé vide n'efface rien : la valeur en vigueur avant cet écran continue de s'appliquer.") }}
        </p>
    </div>

    <div class="p-5 border-b">
        <x-surtitre>{{ __("Identité") }}</x-surtitre>

        <div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <x-input-label for="societe-nom" :value="__('Nom de la plateforme')" />
                <x-text-input wire:model="nom" id="societe-nom" type="text" class="mt-1 block w-full"
                              placeholder="{{ $reglages->nom() }}" />
                <x-input-error :messages="$errors->get('nom')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="societe-raison" :value="__('Raison sociale')" />
                <x-text-input wire:model="raisonSociale" id="societe-raison" type="text" class="mt-1 block w-full"
                              placeholder="{{ $reglages->raisonSociale() }}" />
                <x-input-error :messages="$errors->get('raisonSociale')" class="mt-2" />
            </div>
            <div class="sm:col-span-2">
                <x-input-label for="societe-activite" :value="__('Activité, en une ligne')" />
                <x-text-input wire:model="activite" id="societe-activite" type="text" class="mt-1 block w-full"
                              placeholder="{{ $reglages->activite() }}" />
                <p class="mt-1 text-xs text-gray-400">{{ __("S'imprime sous le nom, en en-tête de chaque PDF.") }}</p>
                <x-input-error :messages="$errors->get('activite')" class="mt-2" />
            </div>
            <div class="sm:col-span-2">
                <x-input-label for="societe-oeuvre" :value="__('Œuvre caritative associée')" />
                <x-text-input wire:model="oeuvre" id="societe-oeuvre" type="text" class="mt-1 block w-full"
                              placeholder="{{ $reglages->valeur('oeuvre') }}" />
                <x-input-error :messages="$errors->get('oeuvre')" class="mt-2" />
            </div>
        </div>
    </div>

    <div class="p-5 border-b">
        <x-surtitre>{{ __("Immatriculation") }}</x-surtitre>

        <div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <x-input-label for="societe-rccm" :value="__('RCCM')" />
                <x-text-input wire:model="rccm" id="societe-rccm" type="text" class="mt-1 block w-full" />
                <x-input-error :messages="$errors->get('rccm')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="societe-ninea" :value="__('NINEA')" />
                <x-text-input wire:model="ninea" id="societe-ninea" type="text" class="mt-1 block w-full" />
                <x-input-error :messages="$errors->get('ninea')" class="mt-2" />
            </div>
        </div>
    </div>

    <div class="p-5 border-b">
        <x-surtitre>{{ __("Adresse") }}</x-surtitre>

        <div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <x-input-label for="societe-adresse" :value="__('Adresse')" />
                <x-text-input wire:model="adresse" id="societe-adresse" type="text" class="mt-1 block w-full" />
                <x-input-error :messages="$errors->get('adresse')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="societe-ville" :value="__('Ville')" />
                <x-text-input wire:model="ville" id="societe-ville" type="text" class="mt-1 block w-full" />
                <x-input-error :messages="$errors->get('ville')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="societe-pays" :value="__('Pays')" />
                <x-text-input wire:model="pays" id="societe-pays" type="text" class="mt-1 block w-full" />
                <x-input-error :messages="$errors->get('pays')" class="mt-2" />
            </div>
        </div>
    </div>

    <div class="p-5 border-b">
        <x-surtitre>{{ __("Comment la joindre") }}</x-surtitre>
        <p class="mt-1 text-sm text-gray-500">
            {{ __("Ces coordonnées servent à deux choses : le message d'identifiants envoyé à l'investisseur, et l'écran « Contacter le support » pour l'équipe. Un investisseur, lui, est dirigé vers son propre gestionnaire.") }}
        </p>

        <div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <x-input-label for="societe-telephone" :value="__('Téléphone')" />
                <x-text-input wire:model="telephone" id="societe-telephone" type="text" class="mt-1 block w-full" placeholder="+221 77 000 00 00" />
                <x-input-error :messages="$errors->get('telephone')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="societe-whatsapp" :value="__('WhatsApp (si différent)')" />
                <x-text-input wire:model="whatsapp" id="societe-whatsapp" type="text" class="mt-1 block w-full" />
                <x-input-error :messages="$errors->get('whatsapp')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="societe-email" :value="__('Email')" />
                <x-text-input wire:model="email" id="societe-email" type="email" class="mt-1 block w-full" />
                <x-input-error :messages="$errors->get('email')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="societe-site" :value="__('Site web')" />
                <x-text-input wire:model="siteWeb" id="societe-site" type="url" class="mt-1 block w-full" placeholder="https://" />
                <x-input-error :messages="$errors->get('siteWeb')" class="mt-2" />
            </div>
            <div class="sm:col-span-2">
                <x-input-label for="societe-horaires" :value="__('Horaires, en clair')" />
                <x-text-input wire:model="horaires" id="societe-horaires" type="text" class="mt-1 block w-full" placeholder="{{ __('Du lundi au vendredi, 9h – 17h') }}" />
                <p class="mt-1 text-xs text-gray-400">{{ __("Affiché à l'équipe sur l'écran de support. Ne rien mettre ne promet rien.") }}</p>
                <x-input-error :messages="$errors->get('horaires')" class="mt-2" />
            </div>
        </div>
    </div>

    <div class="flex items-center justify-end gap-3 p-5">
        <x-primary-button>{{ __("Enregistrer") }}</x-primary-button>
    </div>
</form>
