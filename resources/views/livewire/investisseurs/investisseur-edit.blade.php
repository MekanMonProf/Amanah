<div class="p-4 sm:p-6 max-w-3xl">
    <a href="{{ route('investisseurs.show', $investisseur) }}" wire:navigate class="text-sm text-gray-500 hover:underline">{{ __("← Retour au dossier") }}</a>

    <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 mt-2 mb-6">
        {{ __("Modifier le dossier") }} — {{ $investisseur->identifiant_externe }}
    </h1>

    <form wire:submit="enregistrer" class="space-y-6">

        {{-- Identité --}}
        <div class="bg-white border rounded-carte p-5">
            <h2 class="font-semibold text-gray-800 mb-4">{{ __("Identité") }}</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="text-sm text-gray-600">{{ __("Type") }}</label>
                    <select wire:model.live="type_personne" class="w-full border rounded px-3 py-2">
                        <option value="physique">{{ __("Personne physique") }}</option>
                        <option value="morale">{{ __("Personne morale (entreprise)") }}</option>
                    </select>
                </div>
                <div></div>
                <div>
                    <label class="text-sm text-gray-600">{{ __("Nom") }}</label>
                    <input type="text" wire:model="nom" class="w-full border rounded px-3 py-2">
                    @error('nom') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="text-sm text-gray-600">{{ __("Prénom") }}</label>
                    <input type="text" wire:model="prenom" class="w-full border rounded px-3 py-2">
                </div>
                <div>
                    <label class="text-sm text-gray-600">{{ __("Date de naissance") }}</label>
                    <input type="date" wire:model="date_naissance" class="w-full border rounded px-3 py-2">
                </div>
                <div>
                    <label class="text-sm text-gray-600">{{ __("Lieu de naissance") }}</label>
                    <input type="text" wire:model="lieu_naissance" class="w-full border rounded px-3 py-2">
                </div>
                <div>
                    <label class="text-sm text-gray-600">{{ __("Nationalité") }}</label>
                    <input type="text" wire:model="nationalite" class="w-full border rounded px-3 py-2">
                </div>
                <div>
                    <label class="text-sm text-gray-600">{{ __("Langue") }}</label>
                    <select wire:model="langue" class="w-full border rounded px-3 py-2">
                        @foreach (\App\Support\Langue::DISPONIBLES as $code => $langueDisponible)
                            <option value="{{ $code }}">{{ $langueDisponible['libelle'] }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-gray-500">
                        {{ __("Langue des messages qui lui sont adressés, et de son portail s'il en a un.") }}
                    </p>
                    <x-input-error :messages="$errors->get('langue')" class="mt-1" />
                </div>
            </div>
        </div>

        {{-- Contact --}}
        <div class="bg-white border rounded-carte p-5">
            <h2 class="font-semibold text-gray-800 mb-4">{{ __("Contact") }}</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="text-sm text-gray-600">{{ __("Téléphone") }}</label>
                    <input type="text" wire:model="telephone" placeholder="{{ __('771234567 ou +33... si étranger') }}" class="w-full border rounded px-3 py-2">
                    @error('telephone') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                    <x-champ-whatsapp champ="whatsapp" drapeau="whatsappIdentique" :actif="$whatsappIdentique" />
                </div>
                <div>
                    <label class="text-sm text-gray-600">{{ __("Email") }}</label>
                    <input type="email" wire:model="email" class="w-full border rounded px-3 py-2">
                    @error('email') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                </div>
                <div class="sm:col-span-2">
                    <label class="text-sm text-gray-600">{{ __("Adresse") }}</label>
                    <input type="text" wire:model="adresse" class="w-full border rounded px-3 py-2">
                </div>
                <div>
                    <label class="text-sm text-gray-600">{{ __("Ville") }}</label>
                    <input type="text" wire:model="ville" class="w-full border rounded px-3 py-2">
                </div>
                <div>
                    <label class="text-sm text-gray-600">{{ __("Pays") }}</label>
                    <input type="text" wire:model="pays" class="w-full border rounded px-3 py-2">
                </div>
            </div>
        </div>

        {{-- Pièce d'identité --}}
        <div class="bg-white border rounded-carte p-5">
            <h2 class="font-semibold text-gray-800 mb-4">{{ __("Pièce d'identité") }}</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="text-sm text-gray-600">{{ __("Type de pièce") }}</label>
                    <input type="text" wire:model="type_identification" placeholder="{{ __('CNI, Passeport...') }}" class="w-full border rounded px-3 py-2">
                </div>
                <div>
                    <label class="text-sm text-gray-600">{{ __("Numéro") }}</label>
                    <input type="text" wire:model="numero_identification" class="w-full border rounded px-3 py-2">
                </div>
                <div>
                    <label class="text-sm text-gray-600">{{ __("Date de délivrance") }}</label>
                    <input type="date" wire:model="date_delivrance_piece" class="w-full border rounded px-3 py-2">
                </div>
                <div>
                    <label class="text-sm text-gray-600">{{ __("Lieu / autorité de délivrance") }}</label>
                    <input type="text" wire:model="lieu_delivrance_piece" placeholder="{{ __('Préfecture de Dakar...') }}" class="w-full border rounded px-3 py-2">
                </div>
                <div>
                    <label class="text-sm text-gray-600">{{ __("Date d'expiration") }}</label>
                    <input type="date" wire:model="date_expiration_piece" class="w-full border rounded px-3 py-2">
                </div>
                <div>
                    <label class="text-sm text-gray-600">{{ __("Scan / photo de la pièce") }}</label>
                    <x-champ-fichier model="piece_identite_upload" accept="image/*" />
                    @error('piece_identite_upload') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                    <div wire:loading wire:target="piece_identite_upload" class="text-xs text-gray-400 mt-1">{{ __("Envoi en cours...") }}</div>
                    @if ($investisseur->piece_identite_path)
                        <a href="{{ \App\Support\Document::lien($investisseur, 'piece_identite_path') }}" target="_blank" class="text-xs text-primaire-700 hover:underline">{{ __("Voir le fichier actuel") }}</a>
                    @endif

                    {{-- Lecture de la bande MRZ. Rien n est ecrit d autorite : on propose,
                         le gestionnaire valide. Voir App\Support\Piece\Mrz. --}}
                    @php($libelles = [
                        'nom' => __('Nom'),
                        'prenom' => __('Prénom'),
                        'numero_identification' => __('Numéro de pièce'),
                        'type_identification' => __('Type de pièce'),
                        'date_naissance' => __('Date de naissance'),
                        'date_expiration_piece' => __("Date d'expiration"),
                        'nationalite' => __('Nationalité'),
                        'numero_imprime' => __('Numéro imprimé sur la pièce'),
                    ])

                    @if ($propositionsPiece)
                        <div class="mt-3 bg-primaire-50 border border-primaire-200 rounded-champ p-3">
                            <p class="text-sm font-semibold text-primaire-900">
                                {{ __("Pièce lue — :nombre valeur(s) proposée(s)", ['nombre' => count($propositionsPiece)]) }}
                            </p>
                            <ul class="mt-2 text-sm text-primaire-900 space-y-0.5">
                                @foreach ($propositionsPiece as $champ => $valeur)
                                    <li><span class="text-primaire-700">{{ $libelles[$champ] ?? $champ }} :</span> <strong>{{ $valeur }}</strong></li>
                                @endforeach
                            </ul>
                            <div class="mt-3 flex gap-2">
                                <button type="button" wire:click="appliquerPropositionsPiece"
                                        class="text-sm bg-primaire-700 text-white rounded-champ px-3 py-1.5 hover:bg-primaire-800">
                                    {{ __("Remplir les champs") }}
                                </button>
                                <button type="button" wire:click="ignorerPropositionsPiece"
                                        class="text-sm text-gray-600 hover:underline">
                                    {{ __("Ignorer") }}
                                </button>
                            </div>
                        </div>
                    @endif

                    @if ($divergencesPiece)
                        <div class="mt-3 bg-or-50 border border-or-300 rounded-champ p-3">
                            <p class="text-sm font-semibold text-or-700">{{ __("La pièce ne dit pas la même chose que le formulaire") }}</p>
                            <ul class="mt-2 text-sm text-or-700 space-y-0.5">
                                @foreach ($divergencesPiece as $champ => $valeur)
                                    <li>{{ $libelles[$champ] ?? $champ }} : <strong>{{ $valeur }}</strong> {{ __("sur la pièce") }}</li>
                                @endforeach
                            </ul>
                            <p class="mt-2 text-xs text-or-700">{{ __("Rien n'a été modifié — vérifiez laquelle des deux valeurs est la bonne.") }}</p>
                        </div>
                    @endif

                    @if ($nonConfirmesPiece)
                        <div class="mt-3 bg-sky-50 border border-sky-200 rounded-champ p-3">
                            <p class="text-sm font-semibold text-sky-900">{{ __("Lu, mais non confirmé") }}</p>
                            <p class="mt-1 text-xs text-sky-800">
                                {{ __("La clé de contrôle de la pièce n'a pas validé ces valeurs. Comparez-les au document avant de les reprendre.") }}
                            </p>
                            <ul class="mt-2 space-y-1">
                                @foreach ($nonConfirmesPiece as $champ => $valeur)
                                    <li class="flex items-center gap-2 text-sm text-sky-900">
                                        <span>{{ $libelles[$champ] ?? $champ }} : <strong>{{ $valeur }}</strong></span>
                                        <button type="button" wire:click="accepterNonConfirme('{{ $champ }}')"
                                                class="text-xs border border-sky-300 rounded px-2 py-0.5 hover:bg-sky-100">
                                            {{ __("Reprendre") }}
                                        </button>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if ($motifLecturePiece === \App\Support\Piece\LecteurPiece::AUCUNE_MRZ)
                        <p class="mt-2 text-xs text-gray-500">{{ __("Aucune bande lisible sur cette image — saisissez les champs à la main.") }}</p>
                    @elseif ($motifLecturePiece === \App\Support\Piece\LecteurPiece::ECHEC)
                        <p class="mt-2 text-xs text-gray-500">{{ __("La lecture automatique a échoué — saisissez les champs à la main.") }}</p>
                    @endif
                </div>
            </div>
        </div>

        {{-- Convention d'engagement --}}
        <div class="bg-white border rounded-carte p-5">
            <h2 class="font-semibold text-gray-800 mb-1">{{ __("Convention d'engagement") }}</h2>
            <p class="text-xs text-gray-400 mb-4">{{ __("Le formulaire d'adhésion signé par l'investisseur.") }}</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="text-sm text-gray-600">{{ __("Date de signature") }}</label>
                    <input type="date" wire:model="date_signature_convention" class="w-full border rounded px-3 py-2">
                </div>
                <div>
                    <label class="text-sm text-gray-600">{{ __("Document signé (photo / PDF)") }}</label>
                    <x-champ-fichier model="convention_engagement_upload" accept="image/*,.pdf" />
                    @error('convention_engagement_upload') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                    <div wire:loading wire:target="convention_engagement_upload" class="text-xs text-gray-400 mt-1">{{ __("Envoi en cours...") }}</div>
                    @if ($investisseur->convention_engagement_path)
                        <a href="{{ \App\Support\Document::lien($investisseur, 'convention_engagement_path') }}" target="_blank" class="text-xs text-primaire-700 hover:underline">{{ __("Voir le document actuel") }}</a>
                    @endif
                </div>

                {{-- Pièce facultative par défaut : c'est le paramétrage qui décide
                     si son absence signale le dossier comme incomplet. --}}
                <div>
                    <label class="text-sm text-gray-600">{{ __("Procuration") }}</label>
                    <x-champ-fichier model="piece_procuration_upload" accept="image/*,.pdf" />
                    @error('piece_procuration_upload') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                    <div wire:loading wire:target="piece_procuration_upload" class="text-xs text-gray-400 mt-1">{{ __("Envoi en cours...") }}</div>
                    @if ($investisseur->piece_procuration_path)
                        <a href="{{ \App\Support\Document::lien($investisseur, 'piece_procuration_path') }}" target="_blank" class="text-xs text-primaire-700 hover:underline">{{ __("Voir le document actuel") }}</a>
                    @endif
                </div>

                {{-- Pièce facultative par défaut : c'est le paramétrage qui décide
                     si son absence signale le dossier comme incomplet. --}}
                <div>
                    <label class="text-sm text-gray-600">{{ __("Justificatif de domicile") }}</label>
                    <x-champ-fichier model="piece_justificatif_domicile_upload" accept="image/*,.pdf" />
                    @error('piece_justificatif_domicile_upload') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                    <div wire:loading wire:target="piece_justificatif_domicile_upload" class="text-xs text-gray-400 mt-1">{{ __("Envoi en cours...") }}</div>
                    @if ($investisseur->piece_justificatif_domicile_path)
                        <a href="{{ \App\Support\Document::lien($investisseur, 'piece_justificatif_domicile_path') }}" target="_blank" class="text-xs text-primaire-700 hover:underline">{{ __("Voir le document actuel") }}</a>
                    @endif
                </div>

                {{-- Pièce facultative par défaut : c'est le paramétrage qui décide
                     si son absence signale le dossier comme incomplet. --}}
                <div>
                    <label class="text-sm text-gray-600">{{ __("RIB ou coordonnées bancaires") }}</label>
                    <x-champ-fichier model="piece_rib_upload" accept="image/*,.pdf" />
                    @error('piece_rib_upload') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                    <div wire:loading wire:target="piece_rib_upload" class="text-xs text-gray-400 mt-1">{{ __("Envoi en cours...") }}</div>
                    @if ($investisseur->piece_rib_path)
                        <a href="{{ \App\Support\Document::lien($investisseur, 'piece_rib_path') }}" target="_blank" class="text-xs text-primaire-700 hover:underline">{{ __("Voir le document actuel") }}</a>
                    @endif
                </div>
            </div>
        </div>

        {{-- Comptes d'investissement --}}
        <div class="bg-white border rounded-carte p-5">
            <h2 class="font-semibold text-gray-800 mb-1">{{ __("Réinvestissement automatique") }}</h2>
            <p class="text-xs text-gray-400 mb-4">
                {{ __("Réglable par compte (Commercial et Waqf sont indépendants). Activé par défaut à l'ouverture d'un compte.") }}
            </p>

            @if ($compteCommercialId)
                <div class="flex items-center justify-between py-2 border-b">
                    <div>
                        <span class="px-2 py-1 text-xs font-semibold rounded bg-primaire-100 text-primaire-800">{{ __("Commercial") }}</span>
                    </div>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" wire:model="reinvestissementAutoCommercial" class="w-4 h-4">
                        <span class="text-sm text-gray-600">{{ $reinvestissementAutoCommercial ? __('Activé') : __('Désactivé') }}</span>
                    </label>
                </div>
            @else
                <div class="py-2 border-b text-sm text-gray-400">
                    {{ __("Pas encore de compte Commercial — sera créé au premier achat, avec le réinvestissement activé par défaut.") }}
                </div>
            @endif

            @if ($compteWaqfId)
                <div class="flex items-center justify-between py-2">
                    <div>
                        <span class="px-2 py-1 text-xs font-semibold rounded bg-nuit-900 text-primaire-100">{{ __("Waqf") }}</span>
                    </div>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" wire:model="reinvestissementAutoWaqf" class="w-4 h-4">
                        <span class="text-sm text-gray-600">{{ $reinvestissementAutoWaqf ? __('Activé') : __('Désactivé') }}</span>
                    </label>
                </div>
            @else
                <div class="py-2 text-sm text-gray-400">
                    {{ __("Pas encore de compte Waqf — sera créé au premier achat, avec le réinvestissement activé par défaut.") }}
                </div>
            @endif
        </div>

        {{-- Personne morale --}}
        @if ($type_personne === 'morale')
            <div class="bg-white border rounded-carte p-5">
                <h2 class="font-semibold text-gray-800 mb-4">{{ __("Informations entreprise") }}</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="sm:col-span-2">
                        <label class="text-sm text-gray-600">{{ __("Raison sociale") }}</label>
                        <input type="text" wire:model="raison_sociale" class="w-full border rounded px-3 py-2">
                    </div>
                    <div>
                        <label class="text-sm text-gray-600">RCCM</label>
                        <input type="text" wire:model="rccm" class="w-full border rounded px-3 py-2">
                    </div>
                    <div>
                        <label class="text-sm text-gray-600">NINEA</label>
                        <input type="text" wire:model="ninea" class="w-full border rounded px-3 py-2">
                    </div>
                    <div>
                        <label class="text-sm text-gray-600">{{ __("Représentant légal") }}</label>
                        <input type="text" wire:model="representant_legal_nom" class="w-full border rounded px-3 py-2">
                    </div>
                    <div>
                        <label class="text-sm text-gray-600">{{ __("Téléphone du représentant") }}</label>
                        <input type="text" wire:model="representant_legal_telephone" class="w-full border rounded px-3 py-2">
                        <x-champ-whatsapp champ="representant_legal_whatsapp" drapeau="representantWhatsappIdentique" :actif="$representantWhatsappIdentique" />
                    </div>
                </div>
            </div>
        @endif

        {{-- Bénéficiaire désigné --}}
        <div class="bg-white border rounded-carte p-5">
            <h2 class="font-semibold text-gray-800 mb-1">{{ __("Bénéficiaire désigné") }}</h2>
            <p class="text-xs text-gray-400 mb-4">{{ __("Personne à contacter en cas de décès, notamment pour les comptes Waqf.") }}</p>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="text-sm text-gray-600">{{ __("Nom") }}</label>
                    <input type="text" wire:model="beneficiaire_nom" class="w-full border rounded px-3 py-2">
                </div>
                <div>
                    <label class="text-sm text-gray-600">{{ __("Lien") }}</label>
                    <input type="text" wire:model="beneficiaire_lien" placeholder="{{ __('Ex :') }} Épouse, Fils..." class="w-full border rounded px-3 py-2">
                </div>
                <div>
                    <label class="text-sm text-gray-600">{{ __("Téléphone") }}</label>
                    <input type="text" wire:model="beneficiaire_telephone" class="w-full border rounded px-3 py-2">
                    <x-champ-whatsapp champ="beneficiaire_whatsapp" drapeau="beneficiaireWhatsappIdentique" :actif="$beneficiaireWhatsappIdentique" />
                </div>
            </div>
        </div>

        {{-- Notes internes --}}
        <div class="bg-white border rounded-carte p-5">
            <h2 class="font-semibold text-gray-800 mb-1">{{ __("Notes internes") }}</h2>
            <p class="text-xs text-gray-400 mb-3">{{ __("Visibles uniquement par les gestionnaires et administrateurs.") }}</p>
            <textarea wire:model="notes_internes" rows="3" class="w-full border rounded px-3 py-2"></textarea>
        </div>

        <button type="submit" wire:loading.attr="disabled" wire:target="enregistrer"
                class="bg-primaire-700 text-white px-5 py-2 rounded-champ w-full sm:w-auto disabled:opacity-50 disabled:cursor-not-allowed">
            <span wire:loading.remove wire:target="enregistrer">{{ __("Enregistrer les modifications") }}</span>
            <span wire:loading wire:target="enregistrer">{{ __("Enregistrement...") }}</span>
        </button>
    </form>
</div>
