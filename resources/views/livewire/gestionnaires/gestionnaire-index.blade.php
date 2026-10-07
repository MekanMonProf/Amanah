<div class="p-4 sm:p-6">
    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3 mb-6">
        <x-surtitre>{{ __('Gestion') }}</x-surtitre>
        <h1 class="text-2xl sm:text-3xl font-bold text-gray-900">{{ __("Gestionnaires d'actionnaires") }}</h1>
        <button wire:click="{{ $afficherFormulaire ? '$set(\'afficherFormulaire\', false)' : 'ouvrirFormulaire' }}"
                class="bg-primaire-700 text-white px-4 py-2 rounded-champ hover:bg-primaire-800 w-full sm:w-auto">
            {{ $afficherFormulaire ? __('Annuler') : __('+ Nouveau gestionnaire') }}
        </button>
    </div>

    @if ($dernierMotDePasseGenere)
        <div class="bg-or-50 border border-or-300 rounded-champ p-4 mb-6 text-sm text-or-700">
            @if ($emailConcerneParReinit)
                ✓ {!! __("Mot de passe réinitialisé pour <strong>:email</strong> et envoyé par email.", ['email' => e($emailConcerneParReinit)]) !!}
            @else
                {{ __("✓ Gestionnaire créé. Ses identifiants de connexion lui ont été envoyés par email.") }}
            @endif
            <span class="text-xs text-or-700">({{ __("mot de passe généré") }} : <span class="font-mono">{{ $dernierMotDePasseGenere }}</span>, {{ __("au cas où l'email n'arrive pas") }})</span>
        </div>
    @endif

    @if (session('succes_modification'))
        <div class="bg-primaire-50 border border-primaire-200 text-primaire-700 rounded-champ p-3 mb-4 text-sm">
            {{ session('succes_modification') }}
        </div>
    @endif

    @if (session('erreur_orphelin'))
        <div class="bg-red-50 border border-red-200 text-red-800 rounded-champ px-4 py-3 mb-4 text-sm">
            {{ session('erreur_orphelin') }}
        </div>
    @endif

    @if (session('erreur_desactivation'))
        <div class="bg-red-50 border border-red-200 text-red-700 rounded-champ p-3 mb-4 text-sm">
            {{ session('erreur_desactivation') }}
        </div>
    @endif

    @if ($gestionnaireADesactiverId)
        <div class="bg-white border rounded-carte p-5 mb-6">
            <p class="text-sm font-medium text-gray-700 mb-3">{{ __("Réassigner tout le portefeuille puis désactiver") }}</p>
            <form wire:submit="reassignerPortefeuilleEtDesactiver" class="flex flex-col sm:flex-row gap-2 sm:items-end">
                <div class="flex-1">
                    <label class="text-xs text-gray-500">{{ __("Nouveau gestionnaire pour tous ses investisseurs") }}</label>
                    <select wire:model="nouveauGestionnairePourReassignation" class="w-full border rounded px-3 py-2 text-sm">
                        <option value="">{{ __("— Choisir —") }}</option>
                        @foreach ($gestionnaires as $g)
                            @if ($g->id !== $gestionnaireADesactiverId && $g->actif && ! $g->estOrphelin())
                                <option value="{{ $g->id }}">{{ $g->nomComplet() }}</option>
                            @endif
                        @endforeach
                    </select>
                    @error('nouveauGestionnairePourReassignation') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                </div>
                <div class="flex-1">
                    <label class="text-xs text-gray-500">{{ __("Motif (optionnel)") }}</label>
                    <input type="text" wire:model="motifReassignationMasse" class="w-full border rounded px-3 py-2 text-sm">
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="bg-primaire-700 text-white px-4 py-2 rounded-champ text-sm whitespace-nowrap">
                        {{ __("Réassigner et désactiver") }}
                    </button>
                    <button type="button" wire:click="annulerReassignationMasse" class="text-sm text-gray-500 hover:underline">
                        {{ __("Annuler") }}
                    </button>
                </div>
            </form>
        </div>
    @endif

    @if ($gestionnaireEnEditionId)
        <div class="bg-white border rounded-carte p-5 mb-6">
            <p class="text-sm font-medium text-gray-700 mb-3">{{ __("Modifier le profil du gestionnaire") }}</p>
            <form wire:submit="enregistrerModification" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
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
                    <label class="text-sm text-gray-600">{{ __("Email (identifiant de connexion)") }}</label>
                    <input type="email" wire:model="email" class="w-full border rounded px-3 py-2">
                    @error('email') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="text-sm text-gray-600">{{ __("Téléphone") }}</label>
                    <input type="text" wire:model="telephone" placeholder="{{ __('771234567 ou +33... si étranger') }}" class="w-full border rounded px-3 py-2">
                    @error('telephone') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                    <x-champ-whatsapp champ="whatsapp" drapeau="whatsappIdentique" :actif="$whatsappIdentique" />
                </div>
                <div class="sm:col-span-2 flex gap-2">
                    <button type="submit" class="bg-primaire-700 text-white px-4 py-2 rounded-champ w-full sm:w-auto">
                        {{ __("Enregistrer les modifications") }}
                    </button>
                    <button type="button" wire:click="annulerModification" class="text-sm text-gray-500 hover:underline">
                        {{ __("Annuler") }}
                    </button>
                </div>
            </form>
        </div>
    @endif

    @if ($afficherFormulaire)
        <div class="bg-white border rounded-carte p-5 mb-6">
            <form wire:submit="creer" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
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
                    <label class="text-sm text-gray-600">{{ __("Email (identifiant de connexion)") }}</label>
                    <input type="email" wire:model="email" class="w-full border rounded px-3 py-2">
                    @error('email') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="text-sm text-gray-600">{{ __("Téléphone") }}</label>
                    <input type="text" wire:model="telephone" placeholder="{{ __('771234567 ou +33... si étranger') }}" class="w-full border rounded px-3 py-2">
                    @error('telephone') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                    <x-champ-whatsapp champ="whatsapp" drapeau="whatsappIdentique" :actif="$whatsappIdentique" />
                </div>
                <div class="sm:col-span-2">
                    <label class="text-sm text-gray-600">{{ __("Mot de passe temporaire") }}</label>
                    <input type="text" wire:model="mot_de_passe" class="w-full border rounded px-3 py-2 font-mono">
                    @error('mot_de_passe') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                    <p class="text-xs text-gray-400 mt-1">{{ __("Généré automatiquement, modifiable si besoin.") }}</p>
                </div>
                <div class="sm:col-span-2">
                    <button type="submit" class="bg-primaire-700 text-white px-4 py-2 rounded-champ w-full sm:w-auto">
                        {{ __("Créer le compte gestionnaire") }}
                    </button>
                </div>
            </form>
        </div>
    @endif

    <table class="w-full bg-white border rounded-carte overflow-hidden">
        <thead class="bg-gray-50 text-start text-sm text-gray-600">
            <tr>
                <th class="p-3">{{ __("Nom") }}</th>
                <th class="p-3">{{ __("Email") }}</th>
                <th class="p-3">{{ __("Téléphone") }}</th>
                <th class="p-3 text-end">{{ __("Investisseurs") }}</th>
                <th class="p-3">{{ __("Statut") }}</th>
                <th class="p-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y">
            @forelse ($gestionnaires as $g)
                <tr class="hover:bg-gray-50">
                    <td class="p-3">
                        {{ $g->nomComplet() }}
                        @if ($g->estOrphelin())
                            <span class="ms-1 px-2 py-0.5 text-xs rounded-full bg-red-100 text-red-700 whitespace-nowrap">{{ __("Compte de connexion supprimé") }}</span>
                        @endif
                    </td>
                    <td class="p-3 text-sm text-gray-600">{{ $g->user?->email ?: '—' }}</td>
                    <td class="p-3 text-sm text-gray-600">
                        {{ $g->user?->telephone ?: '—' }}
                        <x-lien-whatsapp :numero="$g->user?->whatsapp" :telephone="$g->user?->telephone" class="block text-xs" />
                    </td>
                    <td class="p-3 text-end">
                        <a href="{{ route('investisseurs.index') }}?gestionnaire={{ $g->id }}" wire:navigate class="text-primaire-700 hover:underline">
                            {{ $g->investisseurs_count }}
                        </a>
                    </td>
                    <td class="p-3">
                        <span class="px-2 py-1 text-xs rounded-full {{ $g->actif ? 'bg-primaire-100 text-primaire-700' : 'bg-gray-100 text-gray-600' }}">
                            {{ $g->actif ? __('Actif') : __('Inactif') }}
                        </span>
                    </td>
                    <td class="p-3 text-end whitespace-nowrap">
                        @if ($g->estOrphelin())
                            {{-- Sans compte de connexion, aucune de ces actions n a de sens :
                                 il n y a plus de nom a changer ni d adresse ou ecrire. --}}
                            <span class="text-sm text-gray-400">{{ __("Aucune action possible") }}</span>
                        @else
                            <button wire:click="modifier({{ $g->id }})" class="text-sm text-gray-600 hover:underline me-3">
                                {{ __("Modifier") }}
                            </button>
                            <button wire:click="reinitialiserMotDePasse({{ $g->id }})" wire:confirm="{{ __('Générer un nouveau mot de passe temporaire pour :identifiant ?', ['identifiant' => $g->user->email]) }}"
                                    class="text-sm text-primaire-700 hover:underline me-3">
                                {{ __("Réinitialiser le mot de passe") }}
                            </button>
                            <button wire:click="basculerActif({{ $g->id }})" wire:confirm="{{ __('Confirmer le changement de statut ?') }}"
                                    class="text-sm text-gray-500 hover:underline">
                                {{ $g->actif ? __('Désactiver') : __('Réactiver') }}
                            </button>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="p-6 text-center text-gray-400">{{ __("Aucun gestionnaire pour l'instant.") }}</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="mt-4">{{ $gestionnaires->links() }}</div>
</div>
