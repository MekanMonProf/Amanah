<div class="p-4 sm:p-6 max-w-2xl">
    <a href="{{ route('successions.gerer', $defunt) }}" wire:navigate class="text-sm text-gray-500 hover:underline">← Retour à la succession</a>

    <h1 class="text-xl sm:text-2xl font-semibold text-gray-800 mt-2 mb-1">Versement — Succession</h1>
    <p class="text-sm text-gray-500 mb-6">
        Défunt : {{ $defunt->nom }} {{ $defunt->prenom }} ({{ $defunt->identifiant_externe }}) ·
        Compte <span class="font-mono">{{ $compte->numero_compte }}</span>
        @if ($mandataire)
            <br>Mandataire : {{ $mandataire->nom }} {{ $mandataire->prenom }} ({{ $mandataire->lien_parente }})
        @endif
    </p>

    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6 text-sm text-blue-800">
        ℹ️ Les actions de ce compte ont déjà été liquidées. Ce versement remet concrètement l'argent
        disponible au mandataire désigné par la famille.
    </div>

    <div class="bg-white border rounded-lg p-4 mb-6">
        <div class="text-xs text-gray-500 uppercase">Solde disponible</div>
        <div class="text-2xl font-semibold text-gray-800">{{ number_format($compte->solde(), 0, ',', ' ') }} CFA</div>
    </div>

    <form wire:submit="enregistrer" class="bg-white border rounded-lg p-5 shadow-sm space-y-4">
        <div>
            <label class="text-sm text-gray-600">Montant à verser (CFA)</label>
            <input type="number" step="0.01" wire:model="montant" class="w-full border rounded px-3 py-2">
            @error('montant') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
        </div>

        <div>
            <label class="text-sm text-gray-600">Date du versement</label>
            <input type="date" wire:model="date_paiement" class="w-full border rounded px-3 py-2">
            @error('date_paiement') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
        </div>

        <div>
            <label class="text-sm text-gray-600">Mode de paiement</label>
            <select wire:model="mode_paiement" class="w-full border rounded px-3 py-2">
                <option value="Wave">Wave</option>
                <option value="Orange Money">Orange Money</option>
                <option value="Espèces">Espèces</option>
                <option value="Virement bancaire">Virement bancaire</option>
                <option value="Chèque">Chèque</option>
                <option value="Autre">Autre</option>
            </select>
        </div>

        <div>
            <label class="text-sm text-gray-600">Référence (optionnel)</label>
            <input type="text" wire:model="reference" class="w-full border rounded px-3 py-2">
        </div>

        <div>
            <label class="text-sm text-gray-600">Preuve de versement (obligatoire — photo / PDF)</label>
            <input type="file" wire:model="preuve_upload" accept="image/*,.pdf" class="w-full border rounded px-3 py-2">
            @error('preuve_upload') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
            <div wire:loading wire:target="preuve_upload" class="text-xs text-gray-400 mt-1">Envoi en cours...</div>
        </div>

        <button type="submit" wire:loading.attr="disabled" wire:target="enregistrer"
                class="bg-emerald-700 text-white px-5 py-2 rounded-lg w-full sm:w-auto disabled:opacity-50">
            <span wire:loading.remove wire:target="enregistrer">Enregistrer le versement</span>
            <span wire:loading wire:target="enregistrer">Traitement...</span>
        </button>
    </form>
</div>
