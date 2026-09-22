<div class="p-4 sm:p-6 max-w-2xl">
    <a href="{{ route('investisseurs.show', $compteSource->investisseur) }}" wire:navigate class="text-sm text-gray-500 hover:underline">{{ __("← Retour au dossier") }}</a>

    <h1 class="text-xl sm:text-2xl font-semibold text-gray-800 mt-2 mb-1">{{ __("Faire un don") }}</h1>
    <p class="text-sm text-gray-500 mb-6">
        {{ __("Depuis") }} {{ $compteSource->investisseur->nom }} {{ $compteSource->investisseur->prenom }} ·
        <span class="font-mono">{{ $compteSource->numero_compte }}</span> ({{ __(\App\Support\Libelles::categorie($compteSource->categorie)) }})
    </p>

    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6 text-sm text-blue-800">
        ℹ️ {{ __("Un don d'actions transfère la propriété sans contrepartie financière pour le donateur.") }}
        {{ __("Un don de solde déplace de l'argent en interne, entre les deux comptes financiers.") }}
        {!! __("Le destinataire doit avoir un compte de la <strong>même catégorie</strong> (:categorie).", ["categorie" => __(\App\Support\Libelles::categorie($compteSource->categorie))]) !!}
    </div>

    <div class="grid grid-cols-2 gap-4 mb-6">
        <div class="bg-white border rounded-lg p-4">
            <div class="text-xs text-gray-500 uppercase">{{ __("Actions détenues") }}</div>
            <div class="text-xl font-semibold text-gray-800">{{ $compteSource->nombreActions() }}</div>
        </div>
        <div class="bg-white border rounded-lg p-4">
            <div class="text-xs text-gray-500 uppercase">{{ __("Solde disponible") }}</div>
            <div class="text-xl font-semibold text-gray-800">{{ \App\Support\Montant::format($compteSource->solde()) }}&#8239;CFA</div>
        </div>
    </div>

    <form wire:submit="enregistrer" class="bg-white border rounded-lg p-5 shadow-sm space-y-4">

        <div>
            <label class="text-sm text-gray-600">{{ __("Type de don") }}</label>
            <div class="flex gap-3 mt-1">
                <label class="flex-1 flex items-center justify-center gap-2 border rounded-lg px-3 py-2 cursor-pointer {{ $typeDon === 'actions' ? 'border-blue-500 bg-blue-50' : '' }}">
                    <input type="radio" wire:model.live="typeDon" value="actions" class="hidden">
                    <span class="text-sm font-medium">{{ __("Actions") }}</span>
                </label>
                <label class="flex-1 flex items-center justify-center gap-2 border rounded-lg px-3 py-2 cursor-pointer {{ $typeDon === 'solde' ? 'border-blue-500 bg-blue-50' : '' }}">
                    <input type="radio" wire:model.live="typeDon" value="solde" class="hidden">
                    <span class="text-sm font-medium">{{ __("Solde (argent)") }}</span>
                </label>
            </div>
        </div>

        <div>
            <label class="text-sm text-gray-600">{{ __("Investisseur destinataire") }}</label>

            @if ($nomDestinataireChoisi)
                <div class="flex items-center justify-between bg-emerald-50 border border-emerald-200 rounded-lg px-3 py-2 mt-1">
                    <span class="text-sm text-emerald-800">{{ $nomDestinataireChoisi }}</span>
                    <button type="button" wire:click="retirerDestinataire" class="text-xs text-red-600 hover:underline">{{ __("Changer") }}</button>
                </div>
            @else
                <input type="text" wire:model.live.debounce.300ms="rechercheDestinataire"
                       placeholder="{{ __('Rechercher par nom ou identifiant...') }}"
                       class="w-full border rounded px-3 py-2 mt-1">
                @error('rechercheDestinataire') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror

                @if ($this->resultatsRecherche->isNotEmpty())
                    <div class="border rounded-lg mt-1 divide-y max-h-48 overflow-y-auto">
                        @foreach ($this->resultatsRecherche as $compte)
                            <button type="button" wire:click="choisirDestinataire({{ $compte->id }})"
                                    class="w-full text-start px-3 py-2 hover:bg-gray-50 text-sm">
                                {{ $compte->investisseur->nom }} {{ $compte->investisseur->prenom }}
                                <span class="text-xs text-gray-400 font-mono">({{ $compte->numero_compte }})</span>
                            </button>
                        @endforeach
                    </div>
                @elseif (strlen($rechercheDestinataire) >= 2)
                    <p class="text-xs text-gray-400 mt-1">{{ __('Aucun compte :categorie trouvé pour cette recherche.', ['categorie' => __(\App\Support\Libelles::categorie($compteSource->categorie))]) }}</p>
                @endif
            @endif
        </div>

        @if ($typeDon === 'actions')
            <div>
                <label class="text-sm text-gray-600">{{ __("Nombre d'actions à donner") }}</label>
                <input type="number" min="1" max="{{ $compteSource->nombreActions() }}" wire:model="nombreActions" class="w-full border rounded px-3 py-2">
                @error('nombreActions') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
            </div>
        @else
            <div>
                <label class="text-sm text-gray-600">{{ __("Montant à donner (CFA)") }}</label>
                <input type="number" step="0.01" min="1" wire:model="montant" class="w-full border rounded px-3 py-2">
                @error('montant') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
            </div>
        @endif

        <div>
            <label class="text-sm text-gray-600">{{ __("Date du don") }}</label>
            <input type="date" wire:model="dateDon" class="w-full border rounded px-3 py-2">
            @error('dateDon') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
        </div>

        <div>
            <label class="text-sm text-gray-600">{{ __("Motif (obligatoire, pour la traçabilité)") }}</label>
            <textarea wire:model="motif" rows="2" class="w-full border rounded px-3 py-2" placeholder="{{ __("Ex : Don familial à l'occasion de...") }}"></textarea>
            @error('motif') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
        </div>

        <div>
            <label class="text-sm text-gray-600">{{ __("Pièce justificative (demande signée, optionnel)") }}</label>
            <x-champ-fichier model="pieceJustificativeUpload" accept="image/*,.pdf" />
            @error('pieceJustificativeUpload') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
            <div wire:loading wire:target="pieceJustificativeUpload" class="text-xs text-gray-400 mt-1">{{ __("Envoi en cours...") }}</div>
        </div>

        <button type="submit" wire:loading.attr="disabled" wire:target="enregistrer"
                class="bg-emerald-700 text-white px-5 py-2 rounded-lg w-full sm:w-auto disabled:opacity-50">
            <span wire:loading.remove wire:target="enregistrer">{{ __("Enregistrer le don") }}</span>
            <span wire:loading wire:target="enregistrer">{{ __("Traitement...") }}</span>
        </button>
    </form>
</div>
