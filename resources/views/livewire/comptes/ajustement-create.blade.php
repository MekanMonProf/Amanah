<div class="p-4 sm:p-6 max-w-2xl">
    <a href="{{ route('investisseurs.show', $compte->investisseur) }}" wire:navigate class="text-sm text-gray-500 hover:underline">← Retour au dossier</a>

    <h1 class="text-xl sm:text-2xl font-semibold text-gray-800 mt-2 mb-1">Ajustement du compte financier</h1>
    <p class="text-sm text-gray-500 mb-6">
        {{ $compte->investisseur->nom }} {{ $compte->investisseur->prenom }} ·
        <span class="font-mono">{{ $compte->numero_compte }}</span> ({{ ucfirst($compte->categorie) }})
    </p>

    <div class="bg-amber-50 border border-amber-200 rounded-lg p-4 mb-6 text-sm text-amber-800">
        ⚠️ Un ajustement <strong>ne modifie ni ne supprime</strong> aucune opération existante — il ajoute une
        nouvelle écriture qui corrige le solde, en gardant une trace complète de l'erreur et de sa correction
        (traçabilité totale, conformément aux règles de gestion).
    </div>

    <div class="bg-white border rounded-lg p-4 mb-6">
        <div class="text-xs text-gray-500 uppercase">Solde actuel du compte</div>
        <div class="text-2xl font-semibold text-gray-800">{{ number_format($compte->solde(), 0, ',', ' ') }} CFA</div>
    </div>

    <form wire:submit="enregistrer" class="bg-white border rounded-lg p-5 shadow-sm space-y-4">
        <div>
            <label class="text-sm text-gray-600">Montant de l'ajustement (CFA)</label>
            <input type="number" step="0.01" wire:model="montant" class="w-full border rounded px-3 py-2"
                   placeholder="Positif pour créditer, négatif pour débiter">
            @error('montant') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
            <p class="text-xs text-gray-400 mt-1">
                Exemple : si un dividende de 99 999 CFA a été crédité par erreur au lieu de 8 000 CFA,
                entrez <strong>-91 999</strong> pour corriger.
            </p>
        </div>

        <div>
            <label class="text-sm text-gray-600">Date de l'ajustement</label>
            <input type="date" wire:model="date_ecriture" class="w-full border rounded px-3 py-2">
            @error('date_ecriture') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
        </div>

        <div>
            <label class="text-sm text-gray-600">Motif (obligatoire, pour l'audit)</label>
            <textarea wire:model="observations" rows="3" class="w-full border rounded px-3 py-2"
                      placeholder="Ex: Correction erreur de saisie — dividende avril 2026 saisi à 99 999 CFA au lieu de 8 000 CFA (6 actions × 1000 CFA)."></textarea>
            @error('observations') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
        </div>

        @if ($montant !== null)
            <div class="bg-gray-50 border rounded-lg p-3 text-sm">
                Nouveau solde après ajustement :
                <strong>{{ number_format($compte->solde() + $montant, 0, ',', ' ') }} CFA</strong>
            </div>
        @endif

        <button type="submit" class="bg-amber-600 text-white px-5 py-2 rounded-lg w-full sm:w-auto hover:bg-amber-700">
            Enregistrer l'ajustement
        </button>
    </form>
</div>
