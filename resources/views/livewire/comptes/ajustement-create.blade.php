<div class="p-4 sm:p-6 max-w-2xl">
    <a href="{{ route('investisseurs.show', $compte->investisseur) }}" wire:navigate class="text-sm text-gray-500 hover:underline">{{ __("← Retour au dossier") }}</a>

    @if ($ecritureDuRecu)
        @php($ecritureCreee = \App\Models\EcritureCompteFinancier::find($ecritureDuRecu))
        <div class="mt-6">
            <x-invite-recu :ecriture="$ecritureCreee"
                           :retour="route('investisseurs.show', $compte->investisseur)"
                           :message="__('Ajustement enregistré. Le solde du compte a été corrigé.', [])" />
        </div>
    @else

    <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 mt-2 mb-1">{{ __("Ajustement du compte financier") }}</h1>
    <p class="text-sm text-gray-500 mb-6">
        {{ $compte->investisseur->nom }} {{ $compte->investisseur->prenom }} ·
        <span class="font-mono">{{ $compte->numero_compte }}</span> ({{ __(\App\Support\Libelles::categorie($compte->categorie)) }})
    </p>

    <div class="bg-or-50 border border-or-300 rounded-champ p-4 mb-6 text-sm text-or-700">
        ⚠️ {!! __("Un ajustement <strong>ne modifie ni ne supprime</strong> aucune opération existante — il ajoute une nouvelle écriture qui corrige le solde, en gardant une trace complète de l'erreur et de sa correction (traçabilité totale, conformément aux règles de gestion).") !!}
    </div>

    <div class="bg-white border rounded-carte p-4 mb-6">
        <div class="text-xs text-gray-500 uppercase">{{ __("Solde actuel du compte") }}</div>
        <div class="text-2xl font-semibold text-gray-800">{{ \App\Support\Montant::format($compte->solde()) }}&#8239;CFA</div>
    </div>

    <form wire:submit="enregistrer" class="bg-white border rounded-carte p-5 space-y-4">
        <div>
            <label class="text-sm text-gray-600">{{ __("Montant de l'ajustement (CFA)") }}</label>
            <input type="number" step="0.01" wire:model="montant" class="w-full border rounded px-3 py-2"
                   placeholder="{{ __('Positif pour créditer, négatif pour débiter') }}">
            @error('montant') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
            <p class="text-xs text-gray-400 mt-1">
                {!! __("Exemple : si un dividende de 99 999 CFA a été crédité par erreur au lieu de 8 000 CFA, entrez <strong>-91 999</strong> pour corriger.") !!}
            </p>
        </div>

        <div>
            <label class="text-sm text-gray-600">{{ __("Date de l'ajustement") }}</label>
            <input type="date" wire:model="date_ecriture" class="w-full border rounded px-3 py-2">
            @error('date_ecriture') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
        </div>

        <div>
            <label class="text-sm text-gray-600">{{ __("Motif (obligatoire, pour l'audit)") }}</label>
            <textarea wire:model="observations" rows="3" class="w-full border rounded px-3 py-2"
                      placeholder="{{ __('Ex : Correction erreur de saisie — dividende avril 2026 saisi à 99 999 CFA au lieu de 8 000 CFA (6 actions × 1000 CFA).') }}"></textarea>
            @error('observations') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
        </div>

        @if ($montant !== null)
            <div class="bg-gray-50 border rounded-champ p-3 text-sm">
                {{ __("Nouveau solde après ajustement :") }}
                <strong>{{ \App\Support\Montant::format($compte->solde() + $montant) }}&#8239;CFA</strong>
            </div>
        @endif

        <button type="submit" class="bg-or-700 text-white px-5 py-2 rounded-champ w-full sm:w-auto hover:bg-or-600">
            {{ __("Enregistrer l'ajustement") }}
        </button>
    </form>

    @endif
</div>
