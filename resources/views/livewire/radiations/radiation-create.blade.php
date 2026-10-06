<div class="p-4 sm:p-6 max-w-2xl">
    <a href="{{ route('investisseurs.show', $compte->investisseur) }}" wire:navigate class="text-sm text-gray-500 hover:underline">{{ __("← Retour au dossier") }}</a>

    @if ($ecritureDuRecu)
        @php($ecritureCreee = \App\Models\EcritureCompteFinancier::find($ecritureDuRecu))
        <div class="mt-6">
            <x-invite-recu :ecriture="$ecritureCreee"
                           :retour="route('investisseurs.show', $compte->investisseur)"
                           :message="__('Radiation enregistrée. Le capital radié est porté au compte financier.', [])" />
        </div>
    @else

    <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 mt-2 mb-1">{{ __("Radiation d'actions") }}</h1>
    <p class="text-sm text-gray-500 mb-6">
        {{ $compte->investisseur->nom }} {{ $compte->investisseur->prenom }} ·
        <span class="font-mono">{{ $compte->numero_compte }}</span> ({{ __(\App\Support\Libelles::categorie($compte->categorie)) }})
    </p>

    {{-- Plus de bandeau « radiation interdite » : l'écran ne s'ouvre plus du tout
         pour une catégorie qui l'interdit, il répond 403 dès le chargement. --}}
    <div class="bg-blue-50 border border-blue-200 rounded-champ p-4 mb-6 text-sm text-blue-800">
        ℹ️ {{ __("Le capital correspondant sera crédité sur le compte financier, en attente de versement effectif via le module Paiement — rien n'est payé automatiquement ici.") }}
    </div>

    <div class="bg-white border rounded-carte p-4 mb-6">
        <div class="text-xs text-gray-500 uppercase">{{ __("Actions actuellement détenues") }}</div>
        <div class="text-2xl font-semibold text-gray-800">{{ $actionsDetenues }}</div>
    </div>

    <form wire:submit="enregistrer" class="bg-white border rounded-carte p-5 space-y-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="text-sm text-gray-600">{{ __("Nombre d'actions à radier") }}</label>
                <input type="number" min="1" max="{{ $actionsDetenues }}" wire:model.live="nombre_actions_radiees" class="w-full border rounded px-3 py-2">
                @error('nombre_actions_radiees') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="text-sm text-gray-600">{{ __("Prix unitaire (CFA)") }}</label>
                <input type="number" step="0.01" wire:model.live="prix_unitaire" class="w-full border rounded px-3 py-2">
                @error('prix_unitaire') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
            </div>
        </div>

        @if ($this->montantTotal > 0)
            <div class="bg-primaire-50 border border-primaire-200 rounded-champ p-3 text-center">
                <span class="text-sm text-primaire-700">{{ __("Montant total à créditer :") }}</span>
                <span class="text-lg font-semibold text-primaire-800">{{ \App\Support\Montant::format($this->montantTotal) }}&#8239;CFA</span>
            </div>
        @endif

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="text-sm text-gray-600">{{ __("Date de la radiation") }}</label>
                <input type="date" wire:model="date_radiation" class="w-full border rounded px-3 py-2">
                @error('date_radiation') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="text-sm text-gray-600">{{ __("Mois prévisionnel de paiement (optionnel)") }}</label>
                <input type="date" wire:model="mois_previsionnel_paiement" class="w-full border rounded px-3 py-2">
            </div>
        </div>

        <div>
            <label class="text-sm text-gray-600">{{ __("Référence") }}</label>
            <input type="text" wire:model="reference_facture" class="w-full border rounded px-3 py-2">
        </div>

        <div>
            <label class="text-sm text-gray-600">{{ __("Pièce justificative (demande signée, photo / PDF)") }}</label>
            <x-champ-fichier model="piece_justificative_upload" accept="image/*,.pdf" />
            @error('piece_justificative_upload') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
            <div wire:loading wire:target="piece_justificative_upload" class="text-xs text-gray-400 mt-1">{{ __("Envoi en cours...") }}</div>
        </div>

        <div>
            <label class="text-sm text-gray-600">{{ __("Observations") }}</label>
            <textarea wire:model="observations" rows="2" class="w-full border rounded px-3 py-2"></textarea>
        </div>

        <button type="submit" wire:loading.attr="disabled" wire:target="enregistrer"
                class="bg-red-600 text-white px-5 py-2 rounded-champ w-full sm:w-auto hover:bg-red-700 disabled:opacity-50">
            <span wire:loading.remove wire:target="enregistrer">{{ __("Enregistrer la radiation") }}</span>
            <span wire:loading wire:target="enregistrer">{{ __("Traitement...") }}</span>
        </button>
    </form>

    @endif
</div>
