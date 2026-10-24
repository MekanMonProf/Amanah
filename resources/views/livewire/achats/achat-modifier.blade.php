<div class="p-4 sm:p-6 lg:p-8 max-w-3xl">
    <a href="{{ route('investisseurs.show', $this->dossier) }}" wire:navigate class="text-sm text-gray-500 hover:underline">{{ __("← Retour au dossier") }}</a>

    {{-- Le titre dit l acte, la ligne dessous dit sur quoi il porte : la même
         disposition que sur les douze autres formulaires. --}}
    <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 mt-2 mb-1">{{ __("Corriger les mentions de l'achat") }}</h1>
    <p class="text-sm text-gray-500 mb-6">
        {{ $achat->numero_achat }} · {{ $achat->date_achat->format('d/m/Y') }} ·
        {{ $this->dossier->nom }} {{ $this->dossier->prenom }} · {{ $this->dossier->identifiant_externe }}
    </p>

    {{-- Ce que cet écran ne touchera pas, dit avant d'ouvrir le formulaire :
         c'est la question que se pose quiconque arrive ici pour rattraper une
         erreur de montant. --}}
    <div class="bg-gray-50 border border-gray-200 rounded-carte p-5 mb-6">
        <h2 class="font-semibold text-gray-800 mb-1">{{ __("Ce qui reste figé") }}</h2>
        <p class="text-sm text-gray-600 mb-4">
            {{ __("Les actions, le prix et la date ont servi au calcul des dividendes déjà versés et à l'attestation déjà remise. Une erreur sur ces montants se reprend par une radiation ou un ajustement, qui laissent une trace.") }}
        </p>
        <dl class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
            <div>
                <dt class="text-gray-500">{{ __("Type") }}</dt>
                <dd class="font-medium text-gray-800">{{ __(\App\Support\Libelles::typeAchat($achat->type_achat)) }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">{{ __("Actions") }}</dt>
                <dd class="font-medium text-gray-800">{{ \App\Support\Montant::format($achat->nombre_actions) }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">{{ __("Prix unitaire") }}</dt>
                <dd class="font-medium text-gray-800"><bdi class="whitespace-nowrap">{{ \App\Support\Montant::avecDevise($achat->prix_unitaire) }}</bdi></dd>
            </div>
            <div>
                <dt class="text-gray-500">{{ __("Montant") }}</dt>
                <dd class="font-medium text-gray-800"><bdi class="whitespace-nowrap">{{ \App\Support\Montant::avecDevise($achat->montant) }}</bdi></dd>
            </div>
        </dl>
    </div>

    <form wire:submit="enregistrer" class="space-y-6">

        <div class="bg-white border rounded-carte p-5">
            <h2 class="font-semibold text-gray-800 mb-4">{{ __("Paiement") }}</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="text-sm text-gray-600">{{ __("Mode de paiement") }}</label>
                    {{-- La plupart des achats repris de l'ancienne application
                         n'en portent aucun : « non précisé » est une réponse, et
                         la première de la liste, pour qu'ouvrir cet écran
                         n'invente rien. --}}
                    <select wire:model="mode_paiement" class="w-full border rounded px-3 py-2">
                        <option value="">{{ __("Non précisé") }}</option>
                        @foreach ($this->modesProposes as $mode)
                            <option value="{{ $mode }}">{{ __($mode) }}</option>
                        @endforeach
                    </select>
                    @error('mode_paiement') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="text-sm text-gray-600">{{ __("Référence de la facture") }}</label>
                    <input type="text" wire:model="reference_facture" class="w-full border rounded px-3 py-2">
                    @error('reference_facture') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                </div>
            </div>

            <div class="mt-4">
                <label class="text-sm text-gray-600">{{ __("Pièce justificative") }}</label>

                @if ($achat->photo_facture_path)
                    <p class="text-sm text-gray-500 mt-1">
                        {{ __("Pièce actuelle :") }}
                        <a href="{{ \App\Support\Document::lien($achat, 'photo_facture_path') }}" target="_blank" class="text-primaire-700 hover:underline">
                            {{ __("l'ouvrir →") }}
                        </a>
                    </p>
                @else
                    <p class="text-sm text-gray-500 mt-1">{{ __("Aucune pièce jointe pour l'instant.") }}</p>
                @endif

                <input type="file" wire:model="facture_upload" class="w-full border rounded px-3 py-2 mt-2 text-sm">
                <p class="text-xs text-gray-400 mt-1">
                    {{ __("JPG, PNG ou PDF, 5 Mo au plus. Un nouvel envoi remplace la pièce affichée ; l'ancienne reste archivée.") }}
                </p>
                <div wire:loading wire:target="facture_upload" class="text-xs text-gray-500 mt-1">{{ __("Envoi en cours...") }}</div>
                @error('facture_upload') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
            </div>
        </div>

        @if ($achat->estUnPresent())
            {{-- Les mentions d'un présent : qui est honoré, et à quel titre. Le
                 motif (hommage ou cadeau) n'est pas ici — il décide du compte
                 crédité et de la formule de l'attestation. --}}
            <div class="bg-white border rounded-carte p-5">
                <h2 class="font-semibold text-gray-800 mb-1">{{ __("Mentions du présent") }}</h2>
                <p class="text-sm text-gray-500 mb-4">
                    {{ __(":formule — telle que l'attestation la portera.", ['formule' => $achat->formulePresentMajuscule()]) }}
                </p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="text-sm text-gray-600">
                            {{ $achat->estEnMemoire() ? __("Nom et prénom du défunt") : __("Nom et prénom du bénéficiaire") }}
                        </label>
                        <input type="text" wire:model="present_pour" maxlength="150" class="w-full border rounded px-3 py-2 mt-1">
                        @error('present_pour') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="text-sm text-gray-600">
                            {{ __("Lien avec :donateur", ["donateur" => $this->dossier->nom . ' ' . $this->dossier->prenom]) }}
                            <span class="text-gray-400">{{ __("(facultatif)") }}</span>
                        </label>
                        {{-- Les mêmes suggestions qu'à la saisie, et en français :
                             ce lien est recopié tel quel sur l'attestation. --}}
                        <input type="text" wire:model="lien_avec_donateur" maxlength="100" list="liens-present"
                               placeholder="{{ __('Ex :') }} Père, Mère, Épouse, Frère, Ami..."
                               class="w-full border rounded px-3 py-2 mt-1">
                        <datalist id="liens-present">
                            @foreach (['Père', 'Mère', 'Époux', 'Épouse', 'Frère', 'Sœur', 'Fils', 'Fille', 'Oncle', 'Tante', 'Grand-père', 'Grand-mère', 'Ami'] as $lien)
                                <option value="{{ $lien }}"></option>
                            @endforeach
                        </datalist>
                        @error('lien_avec_donateur') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                        <p class="text-xs text-gray-500 mt-1">
                            {{ __("Laissez vide s'il n'y a pas de lien de parenté.") }}
                        </p>
                    </div>
                </div>
            </div>
        @endif

        <div class="bg-white border rounded-carte p-5">
            <h2 class="font-semibold text-gray-800 mb-4">{{ __("Observation") }}</h2>

            @if ($this->observationFigee)
                {{-- Phrase écrite par l'application, et traduite à l'affichage :
                     la récrire à la main la figerait dans une seule langue et
                     effacerait la trace de l'acte qui l'a produite. --}}
                <p class="text-sm text-gray-700 bg-gray-50 border border-gray-200 rounded-champ px-3 py-2">
                    {{ $achat->observation_affichee }}
                </p>
                <p class="text-xs text-gray-400 mt-2">
                    {{ __("Cette observation a été écrite par la plateforme au moment de l'opération. Elle n'est pas modifiable.") }}
                </p>
            @else
                <textarea wire:model="observations" rows="3" class="w-full border rounded px-3 py-2"></textarea>
                @error('observations') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
            @endif
        </div>

        <div class="flex items-center gap-3">
            <button type="submit"
                    wire:loading.attr="disabled" wire:target="enregistrer,facture_upload"
                    class="bg-primaire-700 text-white rounded-champ px-5 py-2.5 hover:bg-primaire-800 disabled:opacity-50">
                {{ __("Enregistrer la correction") }}
            </button>
            <a href="{{ route('investisseurs.show', $this->dossier) }}" wire:navigate class="text-sm text-gray-500 hover:underline">
                {{ __("Annuler") }}
            </a>
        </div>
    </form>
</div>
