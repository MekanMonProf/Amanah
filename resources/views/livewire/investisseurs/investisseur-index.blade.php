<div class="p-4 sm:p-6">
    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3 mb-6">
        <h1 class="text-xl sm:text-2xl font-semibold text-gray-800">Investisseurs</h1>
        <div class="flex gap-2 w-full sm:w-auto">
            <a href="{{ route('export.investisseurs.csv', ['recherche' => $recherche, 'gestionnaire' => $filtreGestionnaireId, 'statut' => $filtreStatut]) }}"
               target="_blank" class="text-sm text-gray-700 border border-gray-300 rounded-lg px-4 py-2 hover:bg-gray-50 whitespace-nowrap text-center">
                Exporter CSV
            </a>
            <a href="{{ route('export.investisseurs.pdf', ['recherche' => $recherche, 'gestionnaire' => $filtreGestionnaireId, 'statut' => $filtreStatut]) }}"
               target="_blank" class="text-sm text-gray-700 border border-gray-300 rounded-lg px-4 py-2 hover:bg-gray-50 whitespace-nowrap text-center">
                Exporter PDF
            </a>
            @if (auth()->user()->role !== 'lecture')
                <button wire:click="$toggle('afficherFormulaire')"
                        class="bg-emerald-700 text-white px-4 py-2 rounded-lg hover:bg-emerald-800 w-full sm:w-auto">
                    {{ $afficherFormulaire ? 'Annuler' : '+ Nouvel investisseur' }}
                </button>
            @endif
        </div>
    </div>

    @if ($afficherFormulaire)
        <div class="bg-white border rounded-lg p-5 mb-6 shadow-sm">
            <form wire:submit="creer" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="text-sm text-gray-600">Identifiant externe</label>
                    <input type="text" value="{{ $this->prochainIdentifiant }}" disabled
                           class="w-full border rounded px-3 py-2 bg-gray-50 text-gray-500">
                    <p class="text-xs text-gray-400 mt-1">Généré automatiquement</p>
                </div>
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
                    <label class="text-sm text-gray-600">Téléphone</label>
                    <input type="text" wire:model="telephone" class="w-full border rounded px-3 py-2">
                </div>
                <div>
                    <label class="text-sm text-gray-600">Email</label>
                    <input type="email" wire:model="email" class="w-full border rounded px-3 py-2">
                    @error('email') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="text-sm text-gray-600">Pays</label>
                    <input type="text" wire:model="pays" class="w-full border rounded px-3 py-2">
                </div>
                @if ($gestionnaires->isNotEmpty())
                    <div>
                        <label class="text-sm text-gray-600">Gestionnaire</label>
                        <select wire:model="gestionnaire_id" class="w-full border rounded px-3 py-2">
                            <option value="">-- Choisir --</option>
                            @foreach ($gestionnaires as $g)
                                <option value="{{ $g->id }}">{{ $g->user->nom }} {{ $g->user->prenom }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div class="col-span-1 sm:col-span-2">
                    <button type="submit" class="bg-emerald-700 text-white px-4 py-2 rounded-lg w-full sm:w-auto">
                        Créer le dossier
                    </button>
                </div>
            </form>
        </div>
    @endif

    <div class="flex flex-col sm:flex-row flex-wrap gap-3 sm:gap-4 mb-4 items-start sm:items-center">
        <input type="text" wire:model.live.debounce.300ms="recherche"
               placeholder="Rechercher par nom ou identifiant..."
               class="border rounded px-3 py-2 w-full sm:w-72">

        <select wire:model.live="filtreStatut" class="border rounded px-3 py-2 w-full sm:w-auto">
            <option value="">Tous les statuts</option>
            <option value="actif">Actif</option>
            <option value="inactif">Inactif</option>
        </select>

        <select wire:model.live="filtreAcces" class="border rounded px-3 py-2 w-full sm:w-auto">
            <option value="">Tous les accès</option>
            <option value="email">Connexion par email</option>
            <option value="telephone">Connexion par téléphone</option>
            <option value="aucun">Pas d'accès portail</option>
        </select>

        @if ($gestionnaires->isNotEmpty())
            <select wire:model.live="filtreGestionnaireId" class="border rounded px-3 py-2 w-full sm:w-auto">
                <option value="">Tous les gestionnaires</option>
                @foreach ($gestionnaires as $g)
                    <option value="{{ $g->id }}">{{ $g->user->nom }} {{ $g->user->prenom }}</option>
                @endforeach
            </select>
        @endif

        @if ($recherche || $filtreStatut || $filtreGestionnaireId || $filtreAcces)
            <button wire:click="reinitialiserFiltres" class="text-xs text-gray-500 hover:underline">
                Réinitialiser
            </button>
        @endif
    </div>

    {{-- Vue mobile : cartes empilées --}}
    <div class="sm:hidden space-y-3">
        @forelse ($investisseurs as $inv)
            <a href="{{ route('investisseurs.show', $inv) }}" wire:navigate
               class="block bg-white border rounded-lg p-4 hover:bg-gray-50">
                <div class="flex justify-between items-start mb-2">
                    <div>
                        <div class="font-medium text-gray-800">{{ $inv->nom }} {{ $inv->prenom }}</div>
                        <div class="text-xs text-gray-500 font-mono">{{ $inv->identifiant_externe }}</div>
                    </div>
                    <span class="px-2 py-1 text-xs rounded-full {{ $inv->statut === 'actif' ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-600' }}">
                        {{ $inv->statut }}
                    </span>
                </div>
                <div class="text-sm text-gray-600">
                    {{ $inv->gestionnaire?->user?->nom ?? 'Sans gestionnaire' }}
                    @if ($inv->pays) · {{ $inv->pays }} @endif
                </div>
                <div class="mt-1">
                    @if ($inv->user?->email)
                        <span class="text-xs text-gray-500">📧 Email</span>
                    @elseif ($inv->user?->telephone)
                        <span class="text-xs text-gray-500">📱 Téléphone</span>
                    @else
                        <span class="text-xs text-gray-400">— Pas d'accès</span>
                    @endif
                </div>
            </a>
        @empty
            <div class="bg-white border rounded-lg p-6 text-center text-gray-400">Aucun investisseur ne correspond aux critères.</div>
        @endforelse
    </div>

    {{-- Vue desktop : tableau triable --}}
    <table class="hidden sm:table w-full bg-white border rounded-lg overflow-hidden">
        <thead class="bg-gray-50 text-left text-sm text-gray-600">
            <tr>
                <th class="p-3 cursor-pointer select-none" wire:click="trierPar('identifiant_externe')">
                    Identifiant {!! $tri === 'identifiant_externe' ? ($direction === 'asc' ? '↑' : '↓') : '' !!}
                </th>
                <th class="p-3 cursor-pointer select-none" wire:click="trierPar('nom')">
                    Nom {!! $tri === 'nom' ? ($direction === 'asc' ? '↑' : '↓') : '' !!}
                </th>
                <th class="p-3">Gestionnaire</th>
                <th class="p-3 cursor-pointer select-none" wire:click="trierPar('pays')">
                    Pays {!! $tri === 'pays' ? ($direction === 'asc' ? '↑' : '↓') : '' !!}
                </th>
                <th class="p-3 cursor-pointer select-none" wire:click="trierPar('statut')">
                    Statut {!! $tri === 'statut' ? ($direction === 'asc' ? '↑' : '↓') : '' !!}
                </th>
                <th class="p-3">Accès</th>
                <th class="p-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y">
            @forelse ($investisseurs as $inv)
                <tr class="hover:bg-gray-50">
                    <td class="p-3 font-mono text-sm">{{ $inv->identifiant_externe }}</td>
                    <td class="p-3">{{ $inv->nom }} {{ $inv->prenom }}</td>
                    <td class="p-3 text-sm text-gray-600">
                        {{ $inv->gestionnaire?->user?->nom ?? '—' }}
                    </td>
                    <td class="p-3 text-sm text-gray-600">{{ $inv->pays }}</td>
                    <td class="p-3">
                        <span class="px-2 py-1 text-xs rounded-full {{ $inv->statut === 'actif' ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-600' }}">
                            {{ $inv->statut }}
                        </span>
                    </td>
                    <td class="p-3 text-sm text-gray-600 whitespace-nowrap">
                        @if ($inv->user?->email)
                            📧 Email
                        @elseif ($inv->user?->telephone)
                            📱 Téléphone
                        @else
                            <span class="text-gray-400">—</span>
                        @endif
                    </td>
                    <td class="p-3 text-right">
                        <a href="{{ route('investisseurs.show', $inv) }}" class="text-emerald-700 text-sm hover:underline">
                            Voir le dossier →
                        </a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="p-6 text-center text-gray-400">Aucun investisseur ne correspond aux critères.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="mt-4">{{ $investisseurs->links() }}</div>

    <div class="mt-8 bg-white border rounded-lg p-4">
        <h2 class="text-sm font-semibold text-gray-700 mb-3">Exports globaux (tous les comptes)</h2>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('export.achats.global.csv') }}" target="_blank" class="text-xs text-gray-600 border border-gray-300 rounded px-3 py-1.5 hover:bg-gray-50">Achats — CSV</a>
            <a href="{{ route('export.achats.global.pdf') }}" target="_blank" class="text-xs text-gray-600 border border-gray-300 rounded px-3 py-1.5 hover:bg-gray-50">Achats — PDF</a>
            <a href="{{ route('export.ecritures.global.csv') }}" target="_blank" class="text-xs text-gray-600 border border-gray-300 rounded px-3 py-1.5 hover:bg-gray-50">Écritures — CSV</a>
            <a href="{{ route('export.ecritures.global.pdf') }}" target="_blank" class="text-xs text-gray-600 border border-gray-300 rounded px-3 py-1.5 hover:bg-gray-50">Écritures — PDF</a>
            <a href="{{ route('export.radiations.global.csv') }}" target="_blank" class="text-xs text-gray-600 border border-gray-300 rounded px-3 py-1.5 hover:bg-gray-50">Radiations — CSV</a>
            <a href="{{ route('export.radiations.global.pdf') }}" target="_blank" class="text-xs text-gray-600 border border-gray-300 rounded px-3 py-1.5 hover:bg-gray-50">Radiations — PDF</a>
        </div>
    </div>
</div>
