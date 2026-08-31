<div class="p-4 sm:p-6 max-w-2xl">
    <a href="{{ route('investisseurs.show', $compte->investisseur) }}" wire:navigate class="text-sm text-gray-500 hover:underline">← Retour au dossier</a>

    <h1 class="text-xl sm:text-2xl font-semibold text-gray-800 mt-2 mb-1">Radiation d'actions</h1>
    <p class="text-sm text-gray-500 mb-6">
        {{ $compte->investisseur->nom }} {{ $compte->investisseur->prenom }} ·
        <span class="font-mono">{{ $compte->numero_compte }}</span> ({{ ucfirst($compte->categorie) }})
    </p>

    @if (!$radiationAutorisee)
        <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6 text-sm text-red-800">
            ⛔ La radiation n'est pas autorisée pour la catégorie <strong>{{ ucfirst($compte->categorie) }}</strong>,
            selon la politique d'investissement en vigueur.
        </div>
    @else
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6 text-sm text-blue-800">
            ℹ️ Le capital correspondant sera crédité sur le compte financier, en attente de versement effectif
            via le module Paiement — rien n'est payé automatiquement ici.
        </div>
    @endif

    <div class="bg-white border rounded-lg p-4 mb-6">
        <div class="text-xs text-gray-500 uppercase">Actions actuellement détenues</div>
        <div class="text-2xl font-semibold text-gray-800">{{ $actionsDetenues }}</div>
    </div>

    <form wire:submit="enregistrer" class="bg-white border rounded-lg p-5 shadow-sm space-y-4 {{ !$radiationAutorisee ? 'opacity-50 pointer-events-none' : '' }}">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="text-sm text-gray-600">Nombre d'actions à radier</label>
                <input type="number" min="1" max="{{ $actionsDetenues }}" wire:model.live="nombre_actions_radiees" class="w-full border rounded px-3 py-2">
                @error('nombre_actions_radiees') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="text-sm text-gray-600">Prix unitaire (CFA)</label>
                <input type="number" step="0.01" wire:model.live="prix_unitaire" class="w-full border rounded px-3 py-2">
                @error('prix_unitaire') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
            </div>
        </div>

        @if ($this->montantTotal > 0)
            <div class="bg-emerald-50 border border-emerald-200 rounded-lg p-3 text-center">
                <span class="text-sm text-emerald-700">Montant total à créditer : </span>
                <span class="text-lg font-semibold text-emerald-800">{{ number_format($this->montantTotal, 0, ',', ' ') }} CFA</span>
            </div>
        @endif

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="text-sm text-gray-600">Date de la radiation</label>
                <input type="date" wire:model="date_radiation" class="w-full border rounded px-3 py-2">
                @error('date_radiation') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="text-sm text-gray-600">Mois prévisionnel de paiement (optionnel)</label>
                <input type="date" wire:model="mois_previsionnel_paiement" class="w-full border rounded px-3 py-2">
            </div>
        </div>

        <div>
            <label class="text-sm text-gray-600">Référence</label>
            <input type="text" wire:model="reference_facture" class="w-full border rounded px-3 py-2">
        </div>

        <div>
            <label class="text-sm text-gray-600">Pièce justificative (demande signée, photo / PDF)</label>
            <input type="file" wire:model="piece_justificative_upload" accept="image/*,.pdf" class="w-full border rounded px-3 py-2">
            @error('piece_justificative_upload') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
            <div wire:loading wire:target="piece_justificative_upload" class="text-xs text-gray-400 mt-1">Envoi en cours...</div>
        </div>

        <div>
            <label class="text-sm text-gray-600">Observations</label>
            <textarea wire:model="observations" rows="2" class="w-full border rounded px-3 py-2"></textarea>
        </div>

        <button type="submit" wire:loading.attr="disabled" wire:target="enregistrer"
                class="bg-red-600 text-white px-5 py-2 rounded-lg w-full sm:w-auto hover:bg-red-700 disabled:opacity-50">
            <span wire:loading.remove wire:target="enregistrer">Enregistrer la radiation</span>
            <span wire:loading wire:target="enregistrer">Traitement...</span>
        </button>
    </form>
</div>
