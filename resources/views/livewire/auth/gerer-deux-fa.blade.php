<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900">{{ __("Authentification à deux facteurs") }}</h2>
        <p class="mt-1 text-sm text-gray-600">
            {{ __("Renforcez la sécurité de votre compte avec une application d'authentification") }}
            (Google Authenticator, Authy...).
        </p>
    </header>

    <div class="mt-6">
        @if ($codesRecuperationGeneres)
            <div class="bg-amber-50 border border-amber-200 rounded-lg p-4 mb-4">
                <p class="text-sm font-semibold text-amber-800 mb-2">
                    {{ __("✓ 2FA activé. Notez ces codes de récupération dans un endroit sûr — ils ne s'afficheront plus jamais. Chacun ne fonctionne qu'une seule fois, en cas de perte de votre téléphone.") }}
                </p>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 font-mono text-sm text-amber-900">
                    @foreach ($codesRecuperationGeneres as $code)
                        <div class="bg-white border border-amber-300 rounded px-2 py-1 text-center">{{ $code }}</div>
                    @endforeach
                </div>
            </div>
        @endif

        @if (auth()->user()->deux_fa_actif)
            <div class="flex items-center justify-between bg-emerald-50 border border-emerald-200 rounded-lg p-4">
                <div>
                    <p class="text-sm font-medium text-emerald-800">{{ __("2FA activé") }}</p>
                    <p class="text-xs text-emerald-600">Depuis le {{ auth()->user()->deux_fa_confirme_le?->format('d/m/Y') }}</p>
                </div>
                @if (! $confirmerDesactivation)
                    <button wire:click="demanderDesactivation" class="text-sm text-red-600 hover:underline">
                        {{ __("Désactiver") }}
                    </button>
                @endif
            </div>

            @if ($confirmerDesactivation)
                <form wire:submit="desactiver" class="mt-4 bg-white border rounded-lg p-4">
                    <label class="text-sm text-gray-600">{{ __("Confirmez avec votre mot de passe pour désactiver le 2FA") }}</label>
                    <x-text-input wire:model="motDePasseDesactivation" type="password" class="block mt-1 w-full" />
                    <x-input-error :messages="$errors->get('motDePasseDesactivation')" class="mt-2" />
                    <div class="flex gap-2 mt-3">
                        <x-danger-button type="submit">{{ __("Confirmer la désactivation") }}</x-danger-button>
                        <x-secondary-button type="button" wire:click="$set('confirmerDesactivation', false)">{{ __("Annuler") }}</x-secondary-button>
                    </div>
                </form>
            @endif

        @elseif ($enCoursActivation)
            <div class="bg-white border rounded-lg p-4">
                <p class="text-sm text-gray-700 mb-3">
                    Scannez ce QR code avec votre application d'authentification, puis entrez le code à 6 chiffres généré.
                </p>
                <div class="flex justify-center mb-4">
                    <img src="{{ $qrCodeSvg }}" alt="QR Code d'activation 2FA" class="border rounded-lg p-2" width="200" height="200">
                </div>
                <p class="text-xs text-gray-400 text-center mb-4">
                    Impossible de scanner ? Clé manuelle : <span class="font-mono">{{ $secretTemporaire }}</span>
                </p>

                <form wire:submit="confirmerActivation" class="max-w-xs mx-auto">
                    <x-input-label for="codeConfirmation" value="Code de confirmation" />
                    <x-text-input wire:model="codeConfirmation" id="codeConfirmation" class="block mt-1 w-full text-center text-lg tracking-widest" inputmode="numeric" autofocus />
                    <x-input-error :messages="$errors->get('codeConfirmation')" class="mt-2" />
                    <x-primary-button type="submit" class="mt-3 w-full justify-center">{{ __("Activer") }}</x-primary-button>
                </form>
            </div>
        @else
            <button wire:click="demarrerActivation" class="bg-emerald-700 text-white px-4 py-2 rounded-lg hover:bg-emerald-800 text-sm">
                {{ __("Activer le 2FA") }}
            </button>
        @endif
    </div>
</section>
