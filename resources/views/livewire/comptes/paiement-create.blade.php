<div class="p-4 sm:p-6 max-w-2xl">
    <a href="{{ route('investisseurs.show', $compte->investisseur) }}" wire:navigate class="text-sm text-gray-500 hover:underline">{{ __("← Retour au dossier") }}</a>

    <h1 class="text-xl sm:text-2xl font-semibold text-gray-800 mt-2 mb-1">{{ __("Paiement à l'investisseur") }}</h1>
    <p class="text-sm text-gray-500 mb-6">
        {{ $compte->investisseur->nom }} {{ $compte->investisseur->prenom }} ·
        <span class="font-mono">{{ $compte->numero_compte }}</span> ({{ __(\App\Support\Libelles::categorie($compte->categorie)) }})
    </p>

    @if (!$versementAutorise)
        <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6 text-sm text-red-800">
            ⛔ {!! __(":operation n'est pas autorisé pour la catégorie <strong>:categorie</strong>, selon la politique d'investissement en vigueur.", ['operation' => $source === 'radiation' ? __('Le versement du capital radié') : __('Le versement de dividendes'), 'categorie' => e(__(\App\Support\Libelles::categorie($compte->categorie)))]) !!}
            @if ($source === 'radiation')
                {{ __("Les fonds restent disponibles sur le compte financier.") }}
            @else
                {{ __("Les fonds ne peuvent être que réinvestis.") }}
            @endif
        </div>
    @else
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6 text-sm text-blue-800">
            @if ($source === 'radiation')
                ℹ️ {{ __("Ce versement correspond au capital d'une radiation d'actions, remis concrètement à l'investisseur.") }}
            @else
                ℹ️ {!! __("Ce versement représente un dividende (ou un solde) que l'investisseur a choisi de <strong>ne pas réinvestir</strong>, et que vous lui remettez concrètement (espèces, transfert...).") !!}
            @endif
        </div>
    @endif

    <div class="bg-white border rounded-lg p-4 mb-6">
        <div class="text-xs text-gray-500 uppercase">{{ __("Solde disponible") }}</div>
        <div class="text-2xl font-semibold text-gray-800">{{ \App\Support\Montant::format($compte->solde()) }}&#8239;CFA</div>
    </div>

    <form wire:submit="enregistrer" class="bg-white border rounded-lg p-5 shadow-sm space-y-4 {{ !$versementAutorise ? 'opacity-50 pointer-events-none' : '' }}">
        <div>
            <label class="text-sm text-gray-600">{{ __("Montant à verser (CFA)") }}</label>
            <input type="number" step="0.01" wire:model="montant" class="w-full border rounded px-3 py-2">
            @error('montant') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
        </div>

        <div>
            <label class="text-sm text-gray-600">{{ __("Date du paiement") }}</label>
            <input type="date" wire:model="date_paiement" class="w-full border rounded px-3 py-2">
            @error('date_paiement') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
        </div>

        <div>
            <label class="text-sm text-gray-600">{{ __("Mode de paiement") }}</label>
            <select wire:model="mode_paiement" class="w-full border rounded px-3 py-2">
                <option value="Wave">{{ __("Wave") }}</option>
                <option value="Orange Money">{{ __("Orange Money") }}</option>
                <option value="Espèces">{{ __("Espèces") }}</option>
                <option value="Virement bancaire">{{ __("Virement bancaire") }}</option>
                <option value="Chèque">{{ __("Chèque") }}</option>
                <option value="Autre">{{ __("Autre") }}</option>
            </select>
        </div>

        <div>
            <label class="text-sm text-gray-600">{{ __("Référence (optionnel)") }}</label>
            <input type="text" wire:model="reference" class="w-full border rounded px-3 py-2">
        </div>

        <div>
            <label class="text-sm text-gray-600">{{ __("Preuve de versement (photo / PDF)") }}</label>
            <x-champ-fichier model="preuve_upload" accept="image/*,.pdf" />
            @error('preuve_upload') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
            <div wire:loading wire:target="preuve_upload" class="text-xs text-gray-400 mt-1">{{ __("Envoi en cours...") }}</div>
            @if ($preuve_upload)
                <p class="text-xs text-emerald-700 mt-1">✓ {{ __("Fichier prêt : :fichier", ["fichier" => $preuve_upload->getClientOriginalName()]) }}</p>
            @endif
        </div>

        <button type="submit" wire:loading.attr="disabled" wire:target="enregistrer"
                class="bg-emerald-700 text-white px-5 py-2 rounded-lg w-full sm:w-auto disabled:opacity-50">
            <span wire:loading.remove wire:target="enregistrer">{{ __("Enregistrer le paiement") }}</span>
            <span wire:loading wire:target="enregistrer">{{ __("Traitement...") }}</span>
        </button>
    </form>
</div>
