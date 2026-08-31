<div class="p-4 sm:p-6 max-w-3xl">
    <a href="{{ route('investisseurs.show', $investisseur) }}" wire:navigate class="text-sm text-gray-500 hover:underline">← Retour au dossier</a>

    <h1 class="text-xl sm:text-2xl font-semibold text-gray-800 mt-2 mb-6">
        Modifier le dossier — {{ $investisseur->identifiant_externe }}
    </h1>

    <form wire:submit="enregistrer" class="space-y-6">

        {{-- Identité --}}
        <div class="bg-white border rounded-lg p-5 shadow-sm">
            <h2 class="font-semibold text-gray-800 mb-4">Identité</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="text-sm text-gray-600">Type</label>
                    <select wire:model.live="type_personne" class="w-full border rounded px-3 py-2">
                        <option value="physique">Personne physique</option>
                        <option value="morale">Personne morale (entreprise)</option>
                    </select>
                </div>
                <div></div>
                <div>
                    <label class="text-sm text-gray-600">Nom</label>
                    <input type="text" wire:model="nom" class="w-full border rounded px-3 py-2">
                    @error('nom') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="text-sm text-gray-600">Prénom</label>
                    <input type="text" wire:model="prenom" class="w-full border rounded px-3 py-2">
                </div>
                <div>
                    <label class="text-sm text-gray-600">Date de naissance</label>
                    <input type="date" wire:model="date_naissance" class="w-full border rounded px-3 py-2">
                </div>
                <div>
                    <label class="text-sm text-gray-600">Lieu de naissance</label>
                    <input type="text" wire:model="lieu_naissance" class="w-full border rounded px-3 py-2">
                </div>
                <div>
                    <label class="text-sm text-gray-600">Nationalité</label>
                    <input type="text" wire:model="nationalite" class="w-full border rounded px-3 py-2">
                </div>
            </div>
        </div>

        {{-- Contact --}}
        <div class="bg-white border rounded-lg p-5 shadow-sm">
            <h2 class="font-semibold text-gray-800 mb-4">Contact</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="text-sm text-gray-600">Téléphone</label>
                    <input type="text" wire:model="telephone" class="w-full border rounded px-3 py-2">
                </div>
                <div>
                    <label class="text-sm text-gray-600">Email</label>
                    <input type="email" wire:model="email" class="w-full border rounded px-3 py-2">
                    @error('email') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                </div>
                <div class="sm:col-span-2">
                    <label class="text-sm text-gray-600">Adresse</label>
                    <input type="text" wire:model="adresse" class="w-full border rounded px-3 py-2">
                </div>
                <div>
                    <label class="text-sm text-gray-600">Ville</label>
                    <input type="text" wire:model="ville" class="w-full border rounded px-3 py-2">
                </div>
                <div>
                    <label class="text-sm text-gray-600">Pays</label>
                    <input type="text" wire:model="pays" class="w-full border rounded px-3 py-2">
                </div>
            </div>
        </div>

        {{-- Pièce d'identité --}}
        <div class="bg-white border rounded-lg p-5 shadow-sm">
            <h2 class="font-semibold text-gray-800 mb-4">Pièce d'identité</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="text-sm text-gray-600">Type de pièce</label>
                    <input type="text" wire:model="type_identification" placeholder="CNI, Passeport..." class="w-full border rounded px-3 py-2">
                </div>
                <div>
                    <label class="text-sm text-gray-600">Numéro</label>
                    <input type="text" wire:model="numero_identification" class="w-full border rounded px-3 py-2">
                </div>
                <div>
                    <label class="text-sm text-gray-600">Date de délivrance</label>
                    <input type="date" wire:model="date_delivrance_piece" class="w-full border rounded px-3 py-2">
                </div>
                <div>
                    <label class="text-sm text-gray-600">Lieu / autorité de délivrance</label>
                    <input type="text" wire:model="lieu_delivrance_piece" placeholder="Préfecture de Dakar..." class="w-full border rounded px-3 py-2">
                </div>
                <div>
                    <label class="text-sm text-gray-600">Date d'expiration</label>
                    <input type="date" wire:model="date_expiration_piece" class="w-full border rounded px-3 py-2">
                </div>
                <div>
                    <label class="text-sm text-gray-600">Scan / photo de la pièce</label>
                    <input type="file" wire:model="piece_identite_upload" accept="image/*" class="w-full border rounded px-3 py-2">
                    @error('piece_identite_upload') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                    <div wire:loading wire:target="piece_identite_upload" class="text-xs text-gray-400 mt-1">Envoi en cours...</div>
                    @if ($investisseur->piece_identite_path)
                        <a href="{{ \Illuminate\Support\Facades\Storage::url($investisseur->piece_identite_path) }}" target="_blank" class="text-xs text-emerald-700 hover:underline">Voir le fichier actuel</a>
                    @endif
                </div>
            </div>
        </div>

        {{-- Convention d'engagement --}}
        <div class="bg-white border rounded-lg p-5 shadow-sm">
            <h2 class="font-semibold text-gray-800 mb-1">Convention d'engagement</h2>
            <p class="text-xs text-gray-400 mb-4">Le formulaire d'adhésion signé par l'investisseur.</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="text-sm text-gray-600">Date de signature</label>
                    <input type="date" wire:model="date_signature_convention" class="w-full border rounded px-3 py-2">
                </div>
                <div>
                    <label class="text-sm text-gray-600">Document signé (photo / PDF)</label>
                    <input type="file" wire:model="convention_engagement_upload" accept="image/*,.pdf" class="w-full border rounded px-3 py-2">
                    @error('convention_engagement_upload') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                    <div wire:loading wire:target="convention_engagement_upload" class="text-xs text-gray-400 mt-1">Envoi en cours...</div>
                    @if ($investisseur->convention_engagement_path)
                        <a href="{{ \Illuminate\Support\Facades\Storage::url($investisseur->convention_engagement_path) }}" target="_blank" class="text-xs text-emerald-700 hover:underline">Voir le document actuel</a>
                    @endif
                </div>
            </div>
        </div>

        {{-- Comptes d'investissement --}}
        <div class="bg-white border rounded-lg p-5 shadow-sm">
            <h2 class="font-semibold text-gray-800 mb-1">Réinvestissement automatique</h2>
            <p class="text-xs text-gray-400 mb-4">
                Réglable par compte (Commercial et Waqf sont indépendants). Activé par défaut à l'ouverture d'un compte.
            </p>

            @if ($compteCommercialId)
                <div class="flex items-center justify-between py-2 border-b">
                    <div>
                        <span class="px-2 py-1 text-xs font-semibold rounded bg-blue-100 text-blue-700">Commercial</span>
                    </div>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" wire:model="reinvestissementAutoCommercial" class="w-4 h-4">
                        <span class="text-sm text-gray-600">{{ $reinvestissementAutoCommercial ? 'Activé' : 'Désactivé' }}</span>
                    </label>
                </div>
            @else
                <div class="py-2 border-b text-sm text-gray-400">
                    Pas encore de compte Commercial — sera créé au premier achat, avec le réinvestissement activé par défaut.
                </div>
            @endif

            @if ($compteWaqfId)
                <div class="flex items-center justify-between py-2">
                    <div>
                        <span class="px-2 py-1 text-xs font-semibold rounded bg-purple-100 text-purple-700">Waqf</span>
                    </div>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" wire:model="reinvestissementAutoWaqf" class="w-4 h-4">
                        <span class="text-sm text-gray-600">{{ $reinvestissementAutoWaqf ? 'Activé' : 'Désactivé' }}</span>
                    </label>
                </div>
            @else
                <div class="py-2 text-sm text-gray-400">
                    Pas encore de compte Waqf — sera créé au premier achat, avec le réinvestissement activé par défaut.
                </div>
            @endif
        </div>

        {{-- Personne morale --}}
        @if ($type_personne === 'morale')
            <div class="bg-white border rounded-lg p-5 shadow-sm">
                <h2 class="font-semibold text-gray-800 mb-4">Informations entreprise</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="sm:col-span-2">
                        <label class="text-sm text-gray-600">Raison sociale</label>
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
                        <label class="text-sm text-gray-600">Représentant légal</label>
                        <input type="text" wire:model="representant_legal_nom" class="w-full border rounded px-3 py-2">
                    </div>
                    <div>
                        <label class="text-sm text-gray-600">Téléphone du représentant</label>
                        <input type="text" wire:model="representant_legal_telephone" class="w-full border rounded px-3 py-2">
                    </div>
                </div>
            </div>
        @endif

        {{-- Bénéficiaire désigné --}}
        <div class="bg-white border rounded-lg p-5 shadow-sm">
            <h2 class="font-semibold text-gray-800 mb-1">Bénéficiaire désigné</h2>
            <p class="text-xs text-gray-400 mb-4">Personne à contacter en cas de décès, notamment pour les comptes Waqf.</p>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="text-sm text-gray-600">Nom</label>
                    <input type="text" wire:model="beneficiaire_nom" class="w-full border rounded px-3 py-2">
                </div>
                <div>
                    <label class="text-sm text-gray-600">Lien</label>
                    <input type="text" wire:model="beneficiaire_lien" placeholder="Épouse, Fils..." class="w-full border rounded px-3 py-2">
                </div>
                <div>
                    <label class="text-sm text-gray-600">Téléphone</label>
                    <input type="text" wire:model="beneficiaire_telephone" class="w-full border rounded px-3 py-2">
                </div>
            </div>
        </div>

        {{-- Notes internes --}}
        <div class="bg-white border rounded-lg p-5 shadow-sm">
            <h2 class="font-semibold text-gray-800 mb-1">Notes internes</h2>
            <p class="text-xs text-gray-400 mb-3">Visibles uniquement par les gestionnaires et administrateurs.</p>
            <textarea wire:model="notes_internes" rows="3" class="w-full border rounded px-3 py-2"></textarea>
        </div>

        <button type="submit" wire:loading.attr="disabled" wire:target="enregistrer"
                class="bg-emerald-700 text-white px-5 py-2 rounded-lg w-full sm:w-auto disabled:opacity-50 disabled:cursor-not-allowed">
            <span wire:loading.remove wire:target="enregistrer">Enregistrer les modifications</span>
            <span wire:loading wire:target="enregistrer">Enregistrement...</span>
        </button>
    </form>
</div>
