<div class="p-4 sm:p-6 max-w-2xl">
    <a href="{{ route('investisseurs.show', $compte->investisseur) }}" wire:navigate class="text-sm text-gray-500 hover:underline">{{ __("← Retour au dossier") }}</a>

    <h1 class="text-xl sm:text-2xl font-semibold text-gray-800 mt-2 mb-1">{{ __("Complément financier") }}</h1>
    <p class="text-sm text-gray-500 mb-6">
        {{ $compte->investisseur->nom }} {{ $compte->investisseur->prenom }} ·
        <span class="font-mono">{{ $compte->numero_compte }}</span> ({{ __(\App\Support\Libelles::categorie($compte->categorie)) }})
    </p>

    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6 text-sm text-blue-800">
        ℹ️ {{ __("L'investisseur verse un montant pour compléter son solde et acheter immédiatement une ou plusieurs actions, sans attendre la prochaine distribution de dividendes (règle de gestion, section 11).") }}
    </div>

    <div class="grid grid-cols-2 gap-4 mb-6">
        <div class="bg-white border rounded-lg p-4">
            <div class="text-xs text-gray-500 uppercase">{{ __("Solde actuel") }}</div>
            <div class="text-xl font-semibold text-gray-800">{{ \App\Support\Montant::format($compte->solde()) }}&#8239;CFA</div>
        </div>
        <div class="bg-white border rounded-lg p-4">
            <div class="text-xs text-gray-500 uppercase">{{ __("Prix d'une action") }}</div>
            <div class="text-xl font-semibold text-gray-800">{{ \App\Support\Montant::format($prixAction) }}&#8239;CFA</div>
        </div>
    </div>

    @if ($resultatNbAchats !== null)
        <div class="bg-emerald-50 border border-emerald-200 rounded-lg p-4 mb-6 text-sm text-emerald-800">
            @if ($resultatNbAchats > 0)
                ✓ {!! __("Versement enregistré : <strong>:nombre</strong> action(s) achetée(s) automatiquement. Solde avant : :avant → après : :apres (reliquat conservé sur le compte).", ['nombre' => $resultatNbAchats, 'avant' => \App\Support\Montant::avecDevise($resultatMontantAvant), 'apres' => \App\Support\Montant::avecDevise($resultatMontantApres)]) !!}
            @else
                ✓ {{ __("Versement enregistré. Le solde (:solde) ne couvre pas encore le prix d'une action complète — il reste disponible sur le compte pour un prochain complément ou le prochain dividende.", ['solde' => \App\Support\Montant::avecDevise($resultatMontantApres)]) }}
            @endif
        </div>
    @endif

    <form wire:submit="enregistrer" class="bg-white border rounded-lg p-5 shadow-sm space-y-4">
        <div>
            <label class="text-sm text-gray-600">{{ __("Montant du versement (CFA)") }}</label>
            <input type="number" step="0.01" wire:model="montant" class="w-full border rounded px-3 py-2">
            @error('montant') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
        </div>

        <div>
            <label class="text-sm text-gray-600">{{ __("Date du versement") }}</label>
            <input type="date" wire:model="date_versement" class="w-full border rounded px-3 py-2">
            @error('date_versement') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
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
            <label class="text-sm text-gray-600">{{ __("Justificatif de paiement (photo / PDF)") }}</label>
            <x-champ-fichier model="piece_justificative_upload" accept="image/*,.pdf" />
            @error('piece_justificative_upload') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
            <div wire:loading wire:target="piece_justificative_upload" class="text-xs text-gray-400 mt-1">{{ __("Envoi en cours...") }}</div>
            @if ($piece_justificative_upload)
                <p class="text-xs text-emerald-700 mt-1">✓ {{ __("Fichier prêt : :fichier", ["fichier" => $piece_justificative_upload->getClientOriginalName()]) }}</p>
            @endif
        </div>

        <button type="submit" wire:loading.attr="disabled" wire:target="enregistrer"
                class="bg-emerald-700 text-white px-5 py-2 rounded-lg w-full sm:w-auto disabled:opacity-50">
            <span wire:loading.remove wire:target="enregistrer">{{ __("Enregistrer et acheter") }}</span>
            <span wire:loading wire:target="enregistrer">{{ __("Traitement...") }}</span>
        </button>
    </form>
</div>
