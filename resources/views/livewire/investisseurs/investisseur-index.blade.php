<div class="p-4 sm:p-6 lg:p-8">
    {{-- Le surtitre, le titre et le sous-titre forment un bloc : laisses freres
         dans le flex, ils se repartissaient sur la largeur et le titre partait
         au milieu de l ecran, loin du surtitre qui l annonce. --}}
    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-3 mb-6">
        <div>
            <x-surtitre>{{ __('Gestion') }}</x-surtitre>
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900">{{ __("Investisseurs") }}</h1>
            <p class="text-sm text-gray-500">{{ __("Les dossiers, leur position et leur accès au portail.") }}</p>
        </div>
        {{-- flex-wrap : sur telephone, le bouton principal passe seul a la ligne --}}
        <div class="flex flex-wrap gap-2 w-full sm:w-auto">
            <a href="{{ route('export.investisseurs.csv', ['recherche' => $recherche, 'gestionnaire' => $filtreGestionnaireId, 'statut' => $filtreStatut]) }}"
               target="_blank" class="text-sm text-gray-700 border border-gray-300 rounded-champ px-4 py-2 hover:bg-gray-50 whitespace-nowrap text-center">
                {{ __("Exporter CSV") }}
            </a>
            <a href="{{ route('export.investisseurs.pdf', ['recherche' => $recherche, 'gestionnaire' => $filtreGestionnaireId, 'statut' => $filtreStatut]) }}"
               target="_blank" class="text-sm text-gray-700 border border-gray-300 rounded-champ px-4 py-2 hover:bg-gray-50 whitespace-nowrap text-center">
                {{ __("Exporter PDF") }}
            </a>
            @if (auth()->user()->role !== 'lecture')
                <button wire:click="$toggle('afficherFormulaire')"
                        class="bg-primaire-700 text-white px-4 py-2 rounded-champ hover:bg-primaire-800 w-full sm:w-auto">
                    {{ $afficherFormulaire ? __("Annuler") : __("+ Nouvel investisseur") }}
                </button>
            @endif
        </div>
    </div>

    @if ($afficherFormulaire)
        <div class="bg-white border rounded-carte p-5 mb-6">
            <form wire:submit="creer" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="text-sm text-gray-600">{{ __("Identifiant externe") }}</label>
                    <input type="text" value="{{ $this->prochainIdentifiant }}" disabled
                           class="w-full border rounded px-3 py-2 bg-gray-50 text-gray-500">
                    <p class="text-xs text-gray-400 mt-1">{{ __("Généré automatiquement") }}</p>
                </div>
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
                    <label class="text-sm text-gray-600">{{ __("Téléphone") }}</label>
                    <input type="text" wire:model="telephone" placeholder="{{ __("771234567 ou +33... si étranger") }}" class="w-full border rounded px-3 py-2">
                    @error('telephone') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                    <x-champ-whatsapp champ="whatsapp" drapeau="whatsappIdentique" :actif="$whatsappIdentique" />
                </div>
                <div>
                    <label class="text-sm text-gray-600">{{ __("Email") }}</label>
                    <input type="email" wire:model="email" class="w-full border rounded px-3 py-2">
                    @error('email') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="text-sm text-gray-600">{{ __("Pays") }}</label>
                    <input type="text" wire:model="pays" class="w-full border rounded px-3 py-2">
                </div>
                @if ($gestionnaires->isNotEmpty())
                    <div>
                        <label class="text-sm text-gray-600">{{ __("Gestionnaire") }}</label>
                        <select wire:model="gestionnaire_id" class="w-full border rounded px-3 py-2">
                            <option value="">{{ __("-- Choisir --") }}</option>
                            @foreach ($gestionnaires as $g)
                                <option value="{{ $g->id }}">{{ $g->nomComplet() }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div class="col-span-1 sm:col-span-2">
                    <button type="submit" class="bg-primaire-700 text-white px-4 py-2 rounded-champ w-full sm:w-auto">
                        {{ __("Créer le dossier") }}
                    </button>
                </div>
            </form>
        </div>
    @endif

    <div class="flex flex-col sm:flex-row flex-wrap gap-3 sm:gap-4 mb-4 items-start sm:items-center">
        <input type="text" wire:model.live.debounce.300ms="recherche"
               placeholder="{{ __("Rechercher par nom ou identifiant...") }}"
               class="border rounded px-3 py-2 w-full sm:w-72">

        <select wire:model.live="filtreStatut" class="border rounded px-3 py-2 w-full sm:w-auto">
            <option value="">{{ __("Tous les statuts") }}</option>
            <option value="actif">{{ __("Actif") }}</option>
            <option value="inactif">{{ __("Inactif") }}</option>
        </select>

        <select wire:model.live="filtreAcces" class="border rounded px-3 py-2 w-full sm:w-auto">
            <option value="">{{ __("Tous les accès") }}</option>
            <option value="email">{{ __("Connexion par email") }}</option>
            <option value="telephone">{{ __("Connexion par téléphone") }}</option>
            <option value="aucun">{{ __("Pas d'accès portail") }}</option>
            <option value="demande">{{ __("Demande un nouvel accès") }}</option>
        </select>

        <select wire:model.live="filtreCompletude" class="border-gray-300 rounded-champ text-sm w-full sm:w-auto">
            <option value="">{{ __("Tous les dossiers") }}</option>
            <option value="incomplets">{{ __("Dossiers incomplets") }}</option>
        </select>

        @if ($gestionnaires->isNotEmpty())
            <select wire:model.live="filtreGestionnaireId" class="border rounded px-3 py-2 w-full sm:w-auto">
                <option value="">{{ __("Tous les gestionnaires") }}</option>
                @foreach ($gestionnaires as $g)
                    <option value="{{ $g->id }}">{{ $g->nomComplet() }}</option>
                @endforeach
            </select>
        @endif

        @if ($recherche || $filtreStatut || $filtreGestionnaireId || $filtreAcces)
            <button wire:click="reinitialiserFiltres" class="text-xs text-gray-500 hover:underline">
                {{ __("Réinitialiser") }}
            </button>
        @endif
    </div>

    {{-- Vue mobile : cartes empilées --}}
    <div class="sm:hidden space-y-3">
        @forelse ($investisseurs as $inv)
            <a href="{{ route('investisseurs.show', $inv) }}" wire:navigate
               class="block bg-white border rounded-carte p-4 hover:bg-gray-50">
                <div class="flex justify-between items-start mb-2">
                    <div>
                        <div class="font-medium text-gray-800">{{ $inv->nom }} {{ $inv->prenom }}</div>
                        <div class="text-xs text-gray-500 font-mono">{{ $inv->identifiant_externe }}</div>
                    </div>
                    <span class="px-2 py-1 text-xs rounded-full {{ $inv->statut === 'actif' ? 'bg-primaire-100 text-primaire-700' : 'bg-gray-100 text-gray-600' }}">
                        {{ __($inv->statut) }}
                    </span>
                </div>
                <div class="text-sm text-gray-600">
                    {{ $inv->gestionnaire?->user?->nom ?? __("Sans gestionnaire") }}
                    @if ($inv->pays) · {{ $inv->pays }} @endif
                </div>
                <div class="mt-1">
                    @if ($inv->user?->email)
                        <span class="text-xs text-gray-500">📧 {{ __("Email") }}</span>
                    @elseif ($inv->user?->telephone)
                        <span class="text-xs text-gray-500">📱 {{ __("Téléphone") }}</span>
                    @else
                        <span class="text-xs text-gray-400">— {{ __("Pas d'accès") }}</span>
                    @endif
                </div>
            </a>
        @empty
            <div class="bg-white border rounded-carte p-6 text-center text-gray-400">{{ __("Aucun investisseur ne correspond aux critères.") }}</div>
        @endforelse
    </div>

    {{-- Vue desktop : tableau triable --}}
    <table class="hidden sm:table w-full bg-white border rounded-carte overflow-hidden">
        <thead class="bg-gray-50 text-start text-sm text-gray-600">
            <tr>
                <th class="p-3 cursor-pointer select-none" wire:click="trierPar('identifiant_externe')">
                    {{ __("Identifiant") }} {!! $tri === 'identifiant_externe' ? ($direction === 'asc' ? '↑' : '↓') : '' !!}
                </th>
                <th class="p-3 cursor-pointer select-none" wire:click="trierPar('nom')">
                    {{ __("Nom") }} {!! $tri === 'nom' ? ($direction === 'asc' ? '↑' : '↓') : '' !!}
                </th>
                <th class="p-3">{{ __("Gestionnaire") }}</th>
                <th class="p-3 cursor-pointer select-none" wire:click="trierPar('pays')">
                    {{ __("Pays") }} {!! $tri === 'pays' ? ($direction === 'asc' ? '↑' : '↓') : '' !!}
                </th>
                <th class="p-3 cursor-pointer select-none" wire:click="trierPar('statut')">
                    {{ __("Statut") }} {!! $tri === 'statut' ? ($direction === 'asc' ? '↑' : '↓') : '' !!}
                </th>
                <th class="p-3">{{ __("Accès") }}</th>
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
                        <span class="px-2 py-1 text-xs rounded-full {{ $inv->statut === 'actif' ? 'bg-primaire-100 text-primaire-700' : 'bg-gray-100 text-gray-600' }}">
                            {{ __($inv->statut) }}
                        </span>
                    </td>
                    <td class="p-3 text-sm text-gray-600 whitespace-nowrap">
                        @if ($inv->user?->email)
                            📧 {{ __("Email") }}
                        @elseif ($inv->user?->telephone)
                            📱 {{ __("Téléphone") }}
                        @else
                            <span class="text-gray-400">—</span>
                        @endif
                    </td>
                    <td class="p-3 text-end">
                        <a href="{{ route('investisseurs.show', $inv) }}" class="text-primaire-700 text-sm hover:underline">
                            {{ __("Voir le dossier →") }}
                        </a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="p-6 text-center text-gray-400">{{ __("Aucun investisseur ne correspond aux critères.") }}</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="mt-4">{{ $investisseurs->links() }}</div>

    {{-- Les exports globaux ont leur propre ecran, atteignable depuis la barre laterale --}}
    <p class="mt-8 text-sm text-gray-500">
        {{ __("Les exports portant sur l'ensemble des comptes se trouvent sur la page Exports.") }}
        <a href="{{ route('exports.index') }}" wire:navigate class="text-primaire-700 hover:underline">{{ __("Y aller") }} &rarr;</a>
    </p>
</div>
