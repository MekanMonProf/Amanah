<div class="p-4 sm:p-6 max-w-2xl">
    <a href="{{ route('investisseurs.show', $investisseur) }}" wire:navigate class="text-sm text-gray-500 hover:underline">← Retour au dossier</a>

    <h1 class="text-xl sm:text-2xl font-semibold text-gray-800 mt-2 mb-1">Déclarer un décès</h1>
    <p class="text-sm text-gray-500 mb-6">{{ $investisseur->nom }} {{ $investisseur->prenom }} ({{ $investisseur->identifiant_externe }})</p>

    <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6 text-sm text-red-800">
        ⚠️ Cette action est <strong>irréversible depuis l'interface</strong> et gèle immédiatement tous les comptes
        de cet investisseur : plus aucun achat, complément, paiement, don ou radiation ne sera possible.
        Seule la répartition de succession (étape suivante) reste accessible.
    </div>

    <form wire:submit="declarer" class="bg-white border rounded-lg p-5 shadow-sm space-y-4">
        <div>
            <label class="text-sm text-gray-600">Date du décès</label>
            <input type="date" wire:model="dateDeces" class="w-full border rounded px-3 py-2">
            @error('dateDeces') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
        </div>

        <div>
            <label class="text-sm text-gray-600">Acte de décès (obligatoire — photo ou PDF)</label>
            <input type="file" wire:model="pieceActeDecesUpload" accept="image/*,.pdf" class="w-full border rounded px-3 py-2">
            @error('pieceActeDecesUpload') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
            <div wire:loading wire:target="pieceActeDecesUpload" class="text-xs text-gray-400 mt-1">Envoi en cours...</div>
        </div>

        <label class="flex items-start gap-2">
            <input type="checkbox" wire:model="confirmation" class="mt-1">
            <span class="text-sm text-gray-700">
                Je confirme que ce décès est avéré et documenté, et je comprends que le compte sera
                immédiatement gelé.
            </span>
        </label>
        @error('confirmation') <span class="text-red-600 text-sm block">{{ $message }}</span> @enderror

        <button type="submit" wire:loading.attr="disabled" wire:target="declarer"
                class="bg-red-600 text-white px-5 py-2 rounded-lg w-full sm:w-auto hover:bg-red-700 disabled:opacity-50">
            <span wire:loading.remove wire:target="declarer">Déclarer le décès</span>
            <span wire:loading wire:target="declarer">Traitement...</span>
        </button>
    </form>
</div>
