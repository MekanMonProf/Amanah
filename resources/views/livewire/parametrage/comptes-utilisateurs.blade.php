<div>
    @if (session('succes_comptes'))
        <div class="mb-4 text-sm text-primaire-800 bg-primaire-50 border border-primaire-200 rounded-champ px-4 py-3">
            ✓ {{ session('succes_comptes') }}
        </div>
    @endif

    @if (session('erreur_comptes'))
        <div class="mb-4 text-sm text-red-800 bg-red-50 border border-red-200 rounded-champ px-4 py-3">
            {{ session('erreur_comptes') }}
        </div>
    @endif

    @if ($dernierMotDePasseGenere)
        <div class="mb-4 text-sm text-amber-800 bg-amber-50 border border-amber-200 rounded-champ px-4 py-3">
            {{ __("Mot de passe temporaire :") }}
            <span class="font-mono font-semibold text-base">{{ $dernierMotDePasseGenere }}</span>
            <div class="mt-1 text-xs text-amber-600">
                {{ __("Il ne sera plus affiché après cet écran. Il devra être changé à la première connexion.") }}
            </div>
        </div>
    @endif

    <div class="bg-white border rounded-carte">
        <div class="p-5 border-b flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="font-semibold text-gray-800">{{ __("Comptes de connexion") }}</h2>
                <p class="mt-1 text-sm text-gray-500">
                    {{ __("Tous les comptes, quel que soit leur rôle. Les gestionnaires et les accès investisseur se créent là où vit leur fiche ; ils apparaissent ici pour la vue d'ensemble.") }}
                </p>
            </div>
            <button type="button" wire:click="ouvrirFormulaire"
                    class="text-sm text-white bg-primaire-600 rounded-champ px-4 py-2 hover:bg-primaire-700 whitespace-nowrap">
                {{ __("+ Nouveau compte") }}
            </button>
        </div>

        @if ($afficherFormulaire)
            <form wire:submit="creer" class="p-5 border-b bg-gray-50">
                <p class="text-sm text-gray-600 mb-4">
                    {{ __("Seuls les rôles Direction, Administrateur et Lecture se créent ici : les deux autres dépendent d'une fiche.") }}
                </p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="text-sm text-gray-600">{{ __("Nom") }}</label>
                        <input type="text" wire:model="nom" class="w-full border rounded px-3 py-2">
                        <x-input-error :messages="$errors->get('nom')" class="mt-1" />
                    </div>
                    <div>
                        <label class="text-sm text-gray-600">{{ __("Prénom") }}</label>
                        <input type="text" wire:model="prenom" class="w-full border rounded px-3 py-2">
                    </div>
                    <div>
                        <label class="text-sm text-gray-600">{{ __("Email") }}</label>
                        <input type="email" wire:model="email" class="w-full border rounded px-3 py-2">
                        <x-input-error :messages="$errors->get('email')" class="mt-1" />
                    </div>
                    <div>
                        <label class="text-sm text-gray-600">{{ __("Téléphone") }}</label>
                        <input type="text" wire:model="telephone" class="w-full border rounded px-3 py-2">
                        <x-input-error :messages="$errors->get('telephone')" class="mt-1" />
                    </div>
                    <div>
                        <label class="text-sm text-gray-600">{{ __("Rôle") }}</label>
                        <select wire:model="role" class="w-full border rounded px-3 py-2">
                            @foreach ($rolesAdministrables as $roleDisponible)
                                <option value="{{ $roleDisponible }}">{{ __(\App\Support\Modules::libelleRole($roleDisponible)) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="text-sm text-gray-600">{{ __("Langue de l'application") }}</label>
                        <select wire:model="langue" class="w-full border rounded px-3 py-2">
                            @foreach (\App\Support\Langue::DISPONIBLES as $code => $langueDisponible)
                                <option value="{{ $code }}">{{ $langueDisponible['libelle'] }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="mt-4 flex flex-wrap gap-2">
                    <button type="submit" class="text-sm text-white bg-primaire-600 rounded-champ px-4 py-2 hover:bg-primaire-700">
                        {{ __("Créer le compte") }}
                    </button>
                    <button type="button" wire:click="annuler" class="text-sm text-gray-600 border rounded-champ px-4 py-2 hover:bg-gray-100">
                        {{ __("Annuler") }}
                    </button>
                </div>
            </form>
        @endif

        <div class="p-5 border-b flex flex-wrap gap-3">
            <input type="search" wire:model.live.debounce.300ms="recherche"
                   placeholder="{{ __('Rechercher (nom, email, téléphone)') }}"
                   class="flex-1 min-w-48 border rounded px-3 py-2 text-sm">
            <select wire:model.live="filtreRole" class="border rounded px-3 py-2 text-sm">
                <option value="">{{ __("Tous les rôles") }}</option>
                @foreach (['direction', 'administrateur', 'gestionnaire', 'lecture', 'investisseur'] as $roleFiltre)
                    <option value="{{ $roleFiltre }}">{{ __(\App\Support\Modules::libelleRole($roleFiltre)) }} ({{ $effectifs[$roleFiltre] ?? 0 }})</option>
                @endforeach
            </select>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 text-xs text-gray-500 uppercase">
                        <th class="text-start font-medium p-3">{{ __("Nom") }}</th>
                        <th class="text-start font-medium p-3">{{ __("Identifiant") }}</th>
                        <th class="text-start font-medium p-3">{{ __("Rôle") }}</th>
                        <th class="text-start font-medium p-3">{{ __("État") }}</th>
                        <th class="text-end font-medium p-3">{{ __("Actions") }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse ($comptes as $compte)
                        <tr wire:key="compte-{{ $compte->id }}">
                            <td class="p-3">
                                <div class="font-medium text-gray-800">{{ $compte->nom }} {{ $compte->prenom }}</div>
                                @if ($compte->id === auth()->id())
                                    <div class="text-xs text-primaire-700">{{ __("c'est vous") }}</div>
                                @endif
                            </td>
                            <td class="p-3 text-gray-600">{{ $compte->email ?: $compte->telephone ?: '—' }}</td>
                            <td class="p-3">
                                @if ($this->roleModifiable($compte))
                                    <select class="border rounded px-2 py-1 text-sm"
                                            wire:change="changerRole({{ $compte->id }}, $event.target.value)">
                                        @foreach ($rolesAdministrables as $roleDisponible)
                                            <option value="{{ $roleDisponible }}" @selected($compte->role === $roleDisponible)>
                                                {{ __(\App\Support\Modules::libelleRole($roleDisponible)) }}
                                            </option>
                                        @endforeach
                                    </select>
                                @else
                                    <div class="text-gray-700">{{ __(\App\Support\Modules::libelleRole($compte->role)) }}</div>
                                    @php($ou = $this->rattachement($compte))
                                    @if ($ou)
                                        <a href="{{ $ou['route'] }}" wire:navigate class="text-xs text-blue-600 hover:underline">{{ $ou['libelle'] }} →</a>
                                    @endif
                                @endif
                            </td>
                            <td class="p-3">
                                @if ($compte->actif)
                                    <span class="text-xs text-primaire-700 bg-primaire-50 border border-primaire-200 rounded-full px-2 py-0.5">{{ __("Actif") }}</span>
                                @else
                                    <span class="text-xs text-gray-600 bg-gray-100 border border-gray-300 rounded-full px-2 py-0.5">{{ __("Désactivé") }}</span>
                                @endif
                            </td>
                            <td class="p-3 text-end whitespace-nowrap">
                                <button type="button" wire:click="reinitialiserMotDePasse({{ $compte->id }})"
                                        wire:confirm="{{ __('Générer un nouveau mot de passe temporaire pour :nom ?', ['nom' => $compte->nom]) }}"
                                        class="text-xs text-blue-600 border border-blue-300 rounded px-2 py-1 hover:bg-blue-50">
                                    {{ __("Mot de passe") }}
                                </button>
                                {{-- Pas de bouton sur une ligne gestionnaire : il refuserait
                                     à tous les coups, le portefeuille devant d'abord être
                                     réassigné. Le lien vers l'écran Gestionnaires, dans la
                                     colonne du rôle, y conduit. --}}
                                @if ($compte->id !== auth()->id() && $compte->role !== 'gestionnaire')
                                    <button type="button" wire:click="basculerActif({{ $compte->id }})"
                                            class="ms-1 text-xs {{ $compte->actif ? 'text-red-700 border-red-300 hover:bg-red-50' : 'text-primaire-700 border-primaire-300 hover:bg-primaire-50' }} border rounded px-2 py-1">
                                        {{ $compte->actif ? __("Désactiver") : __("Réactiver") }}
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="p-8 text-center text-gray-400">{{ __("Aucun compte ne correspond.") }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
