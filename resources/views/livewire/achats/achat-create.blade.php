<div class="p-4 sm:p-6 max-w-2xl">
    <a href="{{ route('investisseurs.show', $investisseur) }}" wire:navigate class="text-sm text-gray-500 hover:underline">{{ __("← Retour au dossier") }}</a>

    <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 mt-2 mb-1">{{ __("Nouvel achat d'actions") }}</h1>
    <p class="text-sm text-gray-500 mb-6">{{ $investisseur->nom }} {{ $investisseur->prenom }} · {{ $investisseur->identifiant_externe }}</p>

    @php($manquants = $investisseur->champsManquants())
    @if ($manquants)
        {{-- Avertissement et non blocage : arreter la souscription pour une piece
             manquante couterait plus cher que le risque qu on cherche a reduire. --}}
        <div class="bg-amber-50 border border-amber-200 rounded-champ p-4 mb-4 text-sm text-amber-800">
            <p class="font-semibold">{{ __("Le dossier de cet investisseur est incomplet.") }}</p>
            <p class="mt-1">{{ __("Manquent : :liste.", ['liste' => collect($manquants)->map(fn ($m) => __($m))->implode(', ')]) }}</p>
            <p class="mt-1">{{ __("L'achat reste possible, mais le dossier devra être complété.") }}</p>
        </div>
    @endif

    <form wire:submit="enregistrer" class="bg-white border rounded-carte p-5 space-y-4">

        <div>
            <label class="text-sm text-gray-600">{{ __("Catégorie de compte") }}</label>
            <div class="flex gap-3 mt-1">
                <label class="flex-1 flex items-center justify-center gap-2 border rounded-champ px-3 py-2 cursor-pointer {{ $categorie === 'commercial' ? 'border-primaire-500 bg-primaire-50' : '' }}">
                    <input type="radio" wire:model.live="categorie" value="commercial" class="hidden">
                    <span class="text-sm font-medium">{{ __("Commercial") }}</span>
                </label>
                <label class="flex-1 flex items-center justify-center gap-2 border rounded-champ px-3 py-2 cursor-pointer {{ $categorie === 'waqf' ? 'border-primaire-500 bg-primaire-50' : '' }}">
                    <input type="radio" wire:model.live="categorie" value="waqf" class="hidden">
                    <span class="text-sm font-medium">{{ __("Waqf") }}</span>
                </label>
            </div>
            <p class="text-xs text-gray-400 mt-1">
                @if ($faireUnPresent)
                    {{ __("Les actions seront versées au compte institutionnel « :waqf ».", ["waqf" => \App\Models\Investisseur::NOM_WAQF_CARITATIF]) }}
                @else
                    {{ __("Si l'investisseur n'a pas encore de compte :categorie, il sera créé automatiquement.", ["categorie" => $categorie]) }}
                @endif
            </p>
        </div>

        @if ($categorie === 'waqf')
            <div class="border rounded-champ p-4 {{ $faireUnPresent ? 'border-primaire-300 bg-primaire-50' : 'bg-gray-50' }}">
                <label class="flex items-start gap-3 cursor-pointer">
                    <input type="checkbox" wire:model.live="faireUnPresent" class="mt-1 rounded border-gray-300 text-primaire-700">
                    <span>
                        <span class="text-sm font-medium text-gray-800">{{ __("Offrir ces actions en présent (hommage ou cadeau)") }}</span>
                        <span class="block text-xs text-gray-500 mt-0.5">
                            {{ __(":donateur paie l'achat, mais les actions ne sont pas portées à son compte : elles sont comptabilisées au Waqf caritatif « :waqf ». La personne honorée est mentionnée sur l'attestation, sans détenir les actions ni acquérir de droit.", ["donateur" => $investisseur->nom . ' ' . $investisseur->prenom, "waqf" => \App\Models\Investisseur::NOM_WAQF_CARITATIF]) }}
                        </span>
                    </span>
                </label>
                @error('faireUnPresent') <p class="text-red-600 text-sm mt-2">{{ $message }}</p> @enderror

                @if ($faireUnPresent)
                    <div class="mt-4 space-y-3 border-t border-primaire-200 pt-4">
                        <div>
                            <label class="text-sm text-gray-600">{{ __("Motif du présent") }}</label>
                            <div class="flex gap-3 mt-1">
                                <label class="flex-1 flex items-center justify-center gap-2 border rounded-champ px-3 py-2 cursor-pointer bg-white {{ $typePresent === 'memoire' ? 'border-primaire-500' : '' }}">
                                    <input type="radio" wire:model.live="typePresent" value="memoire" class="hidden">
                                    <span class="text-sm">{{ __("À la mémoire d'un défunt") }}</span>
                                </label>
                                <label class="flex-1 flex items-center justify-center gap-2 border rounded-champ px-3 py-2 cursor-pointer bg-white {{ $typePresent === 'honneur' ? 'border-primaire-500' : '' }}">
                                    <input type="radio" wire:model.live="typePresent" value="honneur" class="hidden">
                                    <span class="text-sm">{{ __("Cadeau à une personne vivante") }}</span>
                                </label>
                            </div>
                        </div>

                        <div>
                            <label class="text-sm text-gray-600">{{ $typePresent === 'memoire' ? __("Défunt honoré") : __("Bénéficiaire du cadeau") }}</label>
                            <div class="flex gap-3 mt-1">
                                <label class="flex-1 flex items-center justify-center gap-2 border rounded-champ px-3 py-2 cursor-pointer bg-white {{ $defuntSource === 'interne' ? 'border-primaire-500' : '' }}">
                                    <input type="radio" wire:model.live="defuntSource" value="interne" class="hidden">
                                    <span class="text-sm">{{ __("Investisseur de la plateforme") }}</span>
                                </label>
                                <label class="flex-1 flex items-center justify-center gap-2 border rounded-champ px-3 py-2 cursor-pointer bg-white {{ $defuntSource === 'externe' ? 'border-primaire-500' : '' }}">
                                    <input type="radio" wire:model.live="defuntSource" value="externe" class="hidden">
                                    <span class="text-sm">{{ __("Personne extérieure") }}</span>
                                </label>
                            </div>
                        </div>

                        @if ($defuntSource === 'interne')
                            <div>
                                <select wire:model="defuntInvestisseurId" class="w-full border rounded px-3 py-2 bg-white">
                                    <option value="">{{ $typePresent === 'memoire' ? __("— Sélectionner un investisseur déclaré décédé —") : __("— Sélectionner un investisseur —") }}</option>
                                    @foreach ($this->defuntsDisponibles as $defunt)
                                        <option value="{{ $defunt->id }}">
                                            {{ $defunt->nom }} {{ $defunt->prenom }} ({{ $defunt->identifiant_externe }}){{ $defunt->date_deces ? ' — ' . __('décédé(e) le :date', ['date' => $defunt->date_deces->format('d/m/Y')]) : '' }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('defuntInvestisseurId') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                                @if ($this->defuntsDisponibles->isEmpty())
                                    <p class="text-xs text-amber-700 mt-1">
                                        {{ $typePresent === "memoire" ? __("Aucun investisseur n'est déclaré décédé") : __("Aucun autre investisseur disponible") }} — {{ __("choisissez « Personne extérieure ».") }}
                                    </p>
                                @endif
                            </div>
                        @else
                            <div>
                                <input type="text" wire:model="defuntNom" maxlength="150" placeholder="{{ $typePresent === 'memoire' ? __("Nom et prénom du défunt") : __("Nom et prénom du bénéficiaire") }}" class="w-full border rounded px-3 py-2 bg-white">
                                @error('defuntNom') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                            </div>
                        @endif

                        <div>
                            <label class="text-sm text-gray-600">
                                {{ __("Lien avec :donateur", ["donateur" => $investisseur->nom . ' ' . $investisseur->prenom]) }}
                                <span class="text-gray-400">{{ __("(facultatif)") }}</span>
                            </label>
                            <input type="text" wire:model="lienAvecDonateur" maxlength="100" list="liens-defunt"
                                   placeholder="{{ __('Ex :') }} Père, Mère, Épouse, Frère, Ami..."
                                   class="w-full border rounded px-3 py-2 bg-white mt-1">
                            <datalist id="liens-defunt">
                                <option value="Père"></option>
                                <option value="Mère"></option>
                                <option value="Époux"></option>
                                <option value="Épouse"></option>
                                <option value="Frère"></option>
                                <option value="Sœur"></option>
                                <option value="Fils"></option>
                                <option value="Fille"></option>
                                <option value="Oncle"></option>
                                <option value="Tante"></option>
                                <option value="Grand-père"></option>
                                <option value="Grand-mère"></option>
                                <option value="Ami"></option>
                            </datalist>
                            @error('lienAvecDonateur') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                            <p class="text-xs text-gray-500 mt-1">
                                {{ $typePresent === 'memoire' ? __("Comment le défunt était lié au donateur.") : __("Comment le bénéficiaire est lié au donateur.") }}
                                {{ __("Laissez vide s'il n'y a pas de lien de parenté.") }}
                                {{-- Les suggestions et la saisie restent en francais : ce lien est recopie tel quel sur le certificat, qui reste francais. --}}
                            </p>
                        </div>
                    </div>
                @endif
            </div>
        @endif

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="text-sm text-gray-600">{{ __("Date d'achat") }}</label>
                <input type="date" wire:model="date_achat" class="w-full border rounded px-3 py-2">
                @error('date_achat') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="text-sm text-gray-600">{{ __("Type d'achat") }}</label>
                <select wire:model="type_achat" class="w-full border rounded px-3 py-2">
                    <option value="initial">{{ __("Initial") }}</option>
                    <option value="rajout">{{ __("Rajout") }}</option>
                    <option value="complement">{{ __("Complément") }}</option>
                    <option value="benefice">{{ __("Bénéfice (réinvestissement)") }}</option>
                </select>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="text-sm text-gray-600">{{ __("Nombre d'actions") }}</label>
                <input type="number" min="1" wire:model.live="nombre_actions" class="w-full border rounded px-3 py-2">
                @error('nombre_actions') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="text-sm text-gray-600">{{ __("Prix unitaire (CFA)") }}</label>
                <input type="number" step="0.01" wire:model.live="prix_unitaire" class="w-full border rounded px-3 py-2">
                @error('prix_unitaire') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
            </div>
        </div>

        @if ($montantCalcule)
            <div class="bg-primaire-50 border border-primaire-200 rounded-champ p-3 text-center">
                <span class="text-sm text-primaire-700">{{ __("Montant total :") }}</span>
                <span class="text-lg font-semibold text-primaire-800">{{ \App\Support\Montant::format($montantCalcule) }}&#8239;CFA</span>
            </div>
        @endif

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
            @error('mode_paiement') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
        </div>

        <div>
            <label class="text-sm text-gray-600">{{ __("Référence facture / reçu") }}</label>
            <input type="text" wire:model="reference_facture" class="w-full border rounded px-3 py-2">
        </div>

        <div>
            <label class="text-sm text-gray-600">{{ __("Facture ou reçu (photo / PDF)") }}</label>
            <x-champ-fichier model="facture_upload" accept="image/*,.pdf" />
            @error('facture_upload') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
            <div wire:loading wire:target="facture_upload" class="text-xs text-gray-400 mt-1">{{ __("Envoi en cours...") }}</div>
            @if ($facture_upload)
                <p class="text-xs text-primaire-700 mt-1">✓ {{ __("Fichier prêt : :fichier", ["fichier" => $facture_upload->getClientOriginalName()]) }}</p>
            @endif
        </div>

        <div>
            <label class="text-sm text-gray-600">{{ __("Observations") }}</label>
            <textarea wire:model="observations" rows="2" class="w-full border rounded px-3 py-2"></textarea>
        </div>

        <button type="submit" wire:loading.attr="disabled" wire:target="enregistrer"
                class="bg-primaire-700 text-white px-5 py-2 rounded-champ w-full sm:w-auto disabled:opacity-50 disabled:cursor-not-allowed">
            <span wire:loading.remove wire:target="enregistrer">{{ __("Enregistrer l'achat") }}</span>
            <span wire:loading wire:target="enregistrer">{{ __("Enregistrement...") }}</span>
        </button>
    </form>
</div>
