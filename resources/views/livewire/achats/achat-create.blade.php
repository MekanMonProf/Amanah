<div class="p-4 sm:p-6 max-w-2xl">
    <a href="{{ route('investisseurs.show', $investisseur) }}" wire:navigate class="text-sm text-gray-500 hover:underline">← Retour au dossier</a>

    <h1 class="text-xl sm:text-2xl font-semibold text-gray-800 mt-2 mb-1">Nouvel achat d'actions</h1>
    <p class="text-sm text-gray-500 mb-6">{{ $investisseur->nom }} {{ $investisseur->prenom }} · {{ $investisseur->identifiant_externe }}</p>

    <form wire:submit="enregistrer" class="bg-white border rounded-lg p-5 shadow-sm space-y-4">

        <div>
            <label class="text-sm text-gray-600">Catégorie de compte</label>
            <div class="flex gap-3 mt-1">
                <label class="flex-1 flex items-center justify-center gap-2 border rounded-lg px-3 py-2 cursor-pointer {{ $categorie === 'commercial' ? 'border-blue-500 bg-blue-50' : '' }}">
                    <input type="radio" wire:model.live="categorie" value="commercial" class="hidden">
                    <span class="text-sm font-medium">Commercial</span>
                </label>
                <label class="flex-1 flex items-center justify-center gap-2 border rounded-lg px-3 py-2 cursor-pointer {{ $categorie === 'waqf' ? 'border-purple-500 bg-purple-50' : '' }}">
                    <input type="radio" wire:model.live="categorie" value="waqf" class="hidden">
                    <span class="text-sm font-medium">Waqf</span>
                </label>
            </div>
            <p class="text-xs text-gray-400 mt-1">
                Si l'investisseur n'a pas encore de compte {{ $categorie }}, il sera créé automatiquement.
            </p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="text-sm text-gray-600">Date d'achat</label>
                <input type="date" wire:model="date_achat" class="w-full border rounded px-3 py-2">
                @error('date_achat') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="text-sm text-gray-600">Type d'achat</label>
                <select wire:model="type_achat" class="w-full border rounded px-3 py-2">
                    <option value="initial">Initial</option>
                    <option value="rajout">Rajout</option>
                    <option value="complement">Complément</option>
                    <option value="benefice">Bénéfice (réinvestissement)</option>
                </select>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="text-sm text-gray-600">Nombre d'actions</label>
                <input type="number" min="1" wire:model.live="nombre_actions" class="w-full border rounded px-3 py-2">
                @error('nombre_actions') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="text-sm text-gray-600">Prix unitaire (CFA)</label>
                <input type="number" step="0.01" wire:model.live="prix_unitaire" class="w-full border rounded px-3 py-2">
                @error('prix_unitaire') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
            </div>
        </div>

        @if ($montantCalcule)
            <div class="bg-emerald-50 border border-emerald-200 rounded-lg p-3 text-center">
                <span class="text-sm text-emerald-700">Montant total : </span>
                <span class="text-lg font-semibold text-emerald-800">{{ number_format($montantCalcule, 0, ',', ' ') }} CFA</span>
            </div>
        @endif

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
            @error('mode_paiement') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
        </div>

        <div>
            <label class="text-sm text-gray-600">Référence facture / reçu</label>
            <input type="text" wire:model="reference_facture" class="w-full border rounded px-3 py-2">
        </div>

        <div>
            <label class="text-sm text-gray-600">Facture ou reçu (photo / PDF)</label>
            <input type="file" wire:model="facture_upload" accept="image/*,.pdf" class="w-full border rounded px-3 py-2">
            @error('facture_upload') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
            <div wire:loading wire:target="facture_upload" class="text-xs text-gray-400 mt-1">Envoi en cours...</div>
            @if ($facture_upload)
                <p class="text-xs text-emerald-700 mt-1">✓ Fichier prêt : {{ $facture_upload->getClientOriginalName() }}</p>
            @endif
        </div>

        <div>
            <label class="text-sm text-gray-600">Observations</label>
            <textarea wire:model="observations" rows="2" class="w-full border rounded px-3 py-2"></textarea>
        </div>

        <button type="submit" wire:loading.attr="disabled" wire:target="enregistrer"
                class="bg-emerald-700 text-white px-5 py-2 rounded-lg w-full sm:w-auto disabled:opacity-50 disabled:cursor-not-allowed">
            <span wire:loading.remove wire:target="enregistrer">Enregistrer l'achat</span>
            <span wire:loading wire:target="enregistrer">Enregistrement...</span>
        </button>
    </form>
</div>
