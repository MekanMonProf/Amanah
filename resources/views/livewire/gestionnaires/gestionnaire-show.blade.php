<div class="p-4 sm:p-6 lg:p-8">
    <a href="{{ route('gestionnaires.index') }}" wire:navigate
       class="mb-4 inline-flex items-center gap-1.5 text-sm text-gray-500 transition hover:text-primaire-700">
        ← {{ __("Retour aux gestionnaires") }}
    </a>

    @if ($dernierMotDePasseGenere)
        <div class="mb-6 rounded-champ border border-or-300 bg-or-50 p-4 text-sm text-or-700">
            {!! __("Mot de passe réinitialisé pour <strong>:email</strong> et envoyé par email.", ['email' => e($emailConcerneParReinit)]) !!}
            <span class="text-xs">({{ __("mot de passe généré") }} : <span class="font-mono">{{ $dernierMotDePasseGenere }}</span>, {{ __("au cas où l'email n'arrive pas") }})</span>
        </div>
    @endif

    @if (session('succes_modification'))
        <div class="mb-4 rounded-champ border border-primaire-200 bg-primaire-50 p-3 text-sm text-primaire-700">
            {{ session('succes_modification') }}
        </div>
    @endif

    @if (session('erreur_orphelin'))
        <div class="mb-4 rounded-champ border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            {{ session('erreur_orphelin') }}
        </div>
    @endif

    @if (session('erreur_desactivation'))
        <div class="mb-4 rounded-champ border border-red-200 bg-red-50 p-3 text-sm text-red-700">
            {{ session('erreur_desactivation') }}
        </div>
    @endif

    {{-- L'identité, et d'un coup d'œil si ce compte peut encore servir. --}}
    <x-surtitre>{{ __('Gestionnaire') }}</x-surtitre>
    <div class="mb-1 flex flex-wrap items-center gap-3">
        <h1 class="text-2xl font-bold text-gray-900 sm:text-3xl">{{ $gestionnaire->nomComplet() }}</h1>
        <span class="rounded-full px-2 py-1 text-xs {{ $gestionnaire->actif ? 'bg-primaire-100 text-primaire-700' : 'bg-gray-100 text-gray-600' }}">
            {{ $gestionnaire->actif ? __('Actif') : __('Inactif') }}
        </span>
        @if ($gestionnaire->estOrphelin())
            <span class="rounded-full bg-red-100 px-2 py-1 text-xs text-red-700">{{ __("Compte de connexion supprimé") }}</span>
        @endif
    </div>
    <p class="mb-6 text-sm text-gray-500">
        {{ $gestionnaire->user?->email ?: '—' }}
        @if ($gestionnaire->user?->telephone)
            · {{ $gestionnaire->user->telephone }}
        @endif
        <x-lien-whatsapp :numero="$gestionnaire->user?->whatsapp" :telephone="$gestionnaire->user?->telephone" class="ms-1 text-xs" />
    </p>

    {{--
        Le même bandeau que sur la fiche d'un investisseur, mais pour ce qu'un
        gestionnaire détient vraiment : des dossiers, et ce qu'ils pèsent. C'est
        la question qu'on se pose en arrivant — « combien en suit-il, et
        combien cela représente-t-il ».
    --}}
    <div class="mb-6 overflow-hidden rounded-carte bg-nuit-900 shadow-sm">
        <div class="flex flex-col gap-6 p-6 lg:flex-row lg:items-center lg:gap-10">
            <div class="lg:w-56 lg:shrink-0">
                <x-surtitre class="text-white/40">{{ __("Portefeuille") }}</x-surtitre>
                <p class="mt-1 text-lg font-bold text-white">{{ __("Ce qu'il suit") }}</p>
                <p class="mt-1 text-sm text-white/60">
                    {{ __(":nombre dossier(s) actif(s)", ['nombre' => \App\Support\Montant::format($position['actifs'])]) }}
                </p>
            </div>

            <div class="grid flex-1 grid-cols-1 gap-5 sm:grid-cols-3 lg:gap-6 lg:divide-x lg:divide-white/10 rtl:lg:divide-x-reverse">
                @foreach ([
                    ['etiquette' => __("Dossiers suivis"), 'valeur' => \App\Support\Montant::format($position['dossiers']), 'accent' => false],
                    ['etiquette' => __("Actions détenues"), 'valeur' => \App\Support\Montant::format($position['actions']), 'accent' => false],
                    ['etiquette' => __("Solde des comptes"), 'valeur' => \App\Support\Montant::avecDevise($position['solde']), 'accent' => true],
                ] as $i => $chiffre)
                    <div class="{{ $i > 0 ? 'lg:ps-6' : '' }}">
                        <p class="text-xs font-medium uppercase tracking-wide text-white/50">{{ $chiffre['etiquette'] }}</p>
                        <p class="mt-1 text-xl font-bold sm:text-2xl {{ $chiffre['accent'] ? 'text-primaire-400' : 'text-white' }}">
                            <bdi>{{ $chiffre['valeur'] }}</bdi>
                        </p>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    @if ($gestionnaireADesactiverId === $gestionnaire->id)
        <div class="mb-6 rounded-carte border bg-white p-5">
            <p class="mb-3 text-sm font-medium text-gray-700">{{ __("Réassigner tout le portefeuille puis désactiver") }}</p>
            <form wire:submit="reassignerPortefeuilleEtDesactiver" class="flex flex-col gap-2 sm:flex-row sm:items-end">
                <div class="flex-1">
                    <label class="text-xs text-gray-500">{{ __("Nouveau gestionnaire pour tous ses investisseurs") }}</label>
                    <select wire:model="nouveauGestionnairePourReassignation" class="w-full rounded border px-3 py-2 text-sm">
                        <option value="">{{ __("— Choisir —") }}</option>
                        @foreach ($repreneurs as $repreneur)
                            <option value="{{ $repreneur->id }}">{{ $repreneur->nomComplet() }}</option>
                        @endforeach
                    </select>
                    @error('nouveauGestionnairePourReassignation') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                </div>
                <div class="flex-1">
                    <label class="text-xs text-gray-500">{{ __("Motif (optionnel)") }}</label>
                    <input type="text" wire:model="motifReassignationMasse" class="w-full rounded border px-3 py-2 text-sm">
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="whitespace-nowrap rounded-champ bg-primaire-700 px-4 py-2 text-sm text-white">
                        {{ __("Réassigner et désactiver") }}
                    </button>
                    <button type="button" wire:click="annulerReassignationMasse" class="text-sm text-gray-500 hover:underline">
                        {{ __("Annuler") }}
                    </button>
                </div>
            </form>
        </div>
    @endif

    @if ($gestionnaireEnEditionId === $gestionnaire->id)
        <div class="mb-6 rounded-carte border bg-white p-5">
            <p class="mb-3 text-sm font-medium text-gray-700">{{ __("Modifier le profil du gestionnaire") }}</p>
            <form wire:submit="enregistrerModification" class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="text-sm text-gray-600">{{ __("Nom") }}</label>
                    <input type="text" wire:model="nom" class="w-full rounded border px-3 py-2">
                    @error('nom') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="text-sm text-gray-600">{{ __("Prénom") }}</label>
                    <input type="text" wire:model="prenom" class="w-full rounded border px-3 py-2">
                </div>
                <div>
                    <label class="text-sm text-gray-600">{{ __("Email (identifiant de connexion)") }}</label>
                    <input type="email" wire:model="email" class="w-full rounded border px-3 py-2">
                    @error('email') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="text-sm text-gray-600">{{ __("Téléphone") }}</label>
                    <input type="text" wire:model="telephone" placeholder="{{ __('771234567 ou +33... si étranger') }}" class="w-full rounded border px-3 py-2">
                    @error('telephone') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                    <x-champ-whatsapp champ="whatsapp" drapeau="whatsappIdentique" :actif="$whatsappIdentique" />
                </div>
                <div class="flex gap-2 sm:col-span-2">
                    <button type="submit" class="w-full rounded-champ bg-primaire-700 px-4 py-2 text-white sm:w-auto">
                        {{ __("Enregistrer les modifications") }}
                    </button>
                    <button type="button" wire:click="annulerModification" class="text-sm text-gray-500 hover:underline">
                        {{ __("Annuler") }}
                    </button>
                </div>
            </form>
        </div>
    @endif

    {{-- Les dossiers qu'il suit, nommés — la liste des investisseurs filtrée ne
         dit que leur nombre, et il fallait la quitter pour en ouvrir un. --}}
    @if ($portefeuille->isNotEmpty())
        <x-bloc-repliable :titre="__('Portefeuille')" cle="portefeuille"
                          :nombre="$portefeuille->count()" :replie="$this->estReplie('portefeuille')">
            <div class="overflow-hidden rounded-carte border border-gray-200 bg-white">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[640px] text-sm">
                        <thead class="bg-gray-50 text-start text-xs uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-4 py-3 text-start font-semibold">{{ __("Identifiant") }}</th>
                                <th class="px-4 py-3 text-start font-semibold">{{ __("Nom et prénom") }}</th>
                                <th class="px-4 py-3 text-start font-semibold">{{ __("Téléphone") }}</th>
                                <th class="px-4 py-3 text-end font-semibold">{{ __("Actions détenues") }}</th>
                                <th class="px-4 py-3 text-end font-semibold">{{ __("Solde") }}</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($portefeuille as $investisseur)
                                <tr class="hover:bg-gray-50">
                                    <td class="whitespace-nowrap px-4 py-3 font-mono text-xs text-gray-500">
                                        {{ $investisseur->identifiant_externe }}
                                    </td>
                                    <td class="px-4 py-3 font-semibold text-gray-900">
                                        {{ trim($investisseur->nom . ' ' . $investisseur->prenom) }}
                                        @if ($investisseur->statut !== 'actif')
                                            {{-- Un dossier inactif ou clos reste dans le portefeuille et
                                                 compte dans son volume : le taire le ferait chercher. --}}
                                            <span class="ms-1 rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600">
                                                {{ $investisseur->statut === 'decede' ? __('Décédé') : __('Inactif') }}
                                            </span>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-gray-600">{{ $investisseur->telephone ?: '—' }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-end text-gray-600">
                                        {{ \App\Support\Montant::format($investisseur->comptes->sum(fn ($compte) => $compte->nombreActions())) }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-end text-gray-600">
                                        <bdi>{{ \App\Support\Montant::avecDevise($investisseur->comptes->sum(fn ($compte) => $compte->solde())) }}</bdi>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-end">
                                        <a href="{{ route('investisseurs.show', $investisseur) }}" wire:navigate
                                           class="inline-flex items-center gap-1.5 text-sm text-gray-600 hover:text-primaire-700">
                                            <x-icone nom="dossier" class="h-4 w-4 shrink-0" />
                                            <span class="hidden xl:inline">{{ __("Voir le dossier") }}</span>
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </x-bloc-repliable>
    @else
        <div class="rounded-carte border bg-white p-8 text-center text-gray-400">
            {{ __("Aucun dossier ne lui est assigné.") }}
        </div>
    @endif

    {{-- Ce qui est entré et sorti de son portefeuille. Cet historique existait en
         base depuis le début et ne s'affichait nulle part : on voyait l'état,
         jamais le mouvement. --}}
    @if ($transferts->isNotEmpty())
        <x-bloc-repliable :titre="__('Transferts de dossiers')" cle="transferts"
                          :nombre="$transferts->count()" :replie="$this->estReplie('transferts')">
            <div class="overflow-hidden rounded-carte border border-gray-200 bg-white">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[620px] text-sm">
                        <thead class="bg-gray-50 text-start text-xs uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-4 py-3 text-start font-semibold">{{ __("Date") }}</th>
                                <th class="px-4 py-3 text-start font-semibold">{{ __("Sens") }}</th>
                                <th class="px-4 py-3 text-start font-semibold">{{ __("Dossier") }}</th>
                                <th class="px-4 py-3 text-start font-semibold">{{ __("Motif") }}</th>
                                <th class="px-4 py-3 text-start font-semibold">{{ __("Par") }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($transferts as $transfert)
                                @php($recu = $transfert->nouveau_gestionnaire_id === $gestionnaire->id)
                                <tr class="hover:bg-gray-50">
                                    <td class="whitespace-nowrap px-4 py-3 text-gray-600">
                                        {{ $transfert->date_transfert?->format('d/m/Y') ?: '—' }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3">
                                        {{-- Le vert dit l'entrée, le gris la sortie : un transfert n'est
                                             pas une perte, seulement un mouvement. --}}
                                        <span class="rounded-full px-2 py-0.5 text-xs {{ $recu ? 'bg-primaire-100 text-primaire-700' : 'bg-gray-100 text-gray-600' }}">
                                            @if ($recu)
                                                {{ __("Reçu de :nom", ['nom' => $transfert->ancienGestionnaire?->nomComplet() ?: __('—')]) }}
                                            @else
                                                {{ __("Cédé à :nom", ['nom' => $transfert->nouveauGestionnaire?->nomComplet() ?: __('—')]) }}
                                            @endif
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        @if ($transfert->investisseur)
                                            <a href="{{ route('investisseurs.show', $transfert->investisseur) }}" wire:navigate
                                               class="text-gray-700 hover:text-primaire-700 hover:underline">
                                                <span class="font-mono text-xs text-gray-500">{{ $transfert->investisseur->identifiant_externe }}</span>
                                                {{ trim($transfert->investisseur->nom . ' ' . $transfert->investisseur->prenom) }}
                                            </a>
                                        @else
                                            <span class="text-gray-400">{{ __("Dossier supprimé") }}</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-gray-500">{{ $transfert->motif ?: '—' }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-gray-500">
                                        {{ $transfert->effectuePar ? trim($transfert->effectuePar->nom . ' ' . $transfert->effectuePar->prenom) : '—' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </x-bloc-repliable>
    @endif

    {{-- Les actions en bas, comme sur la fiche d'un investisseur : on vient
         consulter, on agit en partant. --}}
    <div class="mt-8 border-t border-gray-200 pt-6">
        {{-- Pas « Actions » : dans AMANAH le mot désigne les parts sociales, et
             l'anglais le traduit par « Shares ». --}}
        <x-surtitre>{{ __("Agir sur ce compte") }}</x-surtitre>
        @if ($gestionnaire->estOrphelin())
            <p class="mt-2 text-sm text-gray-400">
                {{ __("Sans compte de connexion, il n'y a plus de nom à changer ni d'adresse où écrire. Seule la réassignation de son portefeuille reste possible, depuis la liste.") }}
            </p>
        @else
            <div class="mt-2 flex flex-wrap gap-2">
                <button wire:click="modifier({{ $gestionnaire->id }})"
                        class="inline-flex items-center gap-2 rounded-champ border border-gray-300 bg-white px-4 py-2 text-sm text-gray-700 transition hover:bg-gray-50">
                    <x-icone nom="modifier" class="h-4 w-4 shrink-0" />
                    {{ __("Modifier le profil") }}
                </button>

                <button wire:click="reinitialiserMotDePasse({{ $gestionnaire->id }})"
                        wire:confirm="{{ __('Générer un nouveau mot de passe temporaire pour :identifiant ?', ['identifiant' => $gestionnaire->user->email]) }}"
                        class="inline-flex items-center gap-2 rounded-champ border border-gray-300 bg-white px-4 py-2 text-sm text-gray-700 transition hover:bg-gray-50">
                    <x-icone nom="cle" class="h-4 w-4 shrink-0" />
                    {{ __("Réinitialiser le mot de passe") }}
                </button>

                <button wire:click="basculerActif({{ $gestionnaire->id }})"
                        wire:confirm="{{ __('Confirmer le changement de statut ?') }}"
                        class="inline-flex items-center gap-2 rounded-champ border border-gray-300 bg-white px-4 py-2 text-sm text-gray-700 transition hover:bg-gray-50 hover:text-red-700">
                    <x-icone nom="desactiver" class="h-4 w-4 shrink-0" />
                    {{ $gestionnaire->actif ? __('Désactiver') : __('Réactiver') }}
                </button>
            </div>
        @endif
    </div>
</div>
