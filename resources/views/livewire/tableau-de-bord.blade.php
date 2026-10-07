@php
    // Ce qui attend un geste. Rassemblé ici plutôt que dispersé dans les tuiles :
    // un chiffre d'alerte caché au coin d'une statistique ne fait rien faire.
    $aTraiter = [];

    if ($vue !== 'gestionnaire') {
        if (($nbDossiersIncomplets ?? 0) > 0) {
            $aTraiter[] = [
                'nombre' => $nbDossiersIncomplets,
                'libelle' => __("dossier(s) incomplet(s)"),
                'detail' => __("pièce d'identité, convention ou coordonnées manquantes"),
                'lien' => route('investisseurs.index', ['completude' => 'incomplets']),
                'ton' => 'or',
            ];
        }

        if (($nbInvestisseursSansGestionnaire ?? 0) > 0) {
            $aTraiter[] = [
                'nombre' => $nbInvestisseursSansGestionnaire,
                'libelle' => __("investisseur(s) sans gestionnaire"),
                'detail' => __("personne ne suit ces dossiers"),
                'lien' => route('investisseurs.index'),
                'ton' => 'or',
            ];
        }
    }

    // Celle-ci concerne aussi le gestionnaire, et lui d'abord : c'est lui qui
    // réinitialise l'accès de ses investisseurs.
    if (($nbDemandesAcces ?? 0) > 0) {
        $aTraiter[] = [
            'nombre' => $nbDemandesAcces,
            'libelle' => __("demande(s) de nouvel accès"),
            'detail' => __("un investisseur ne parvient plus à se connecter"),
            'lien' => route('investisseurs.index', ['acces' => 'demande']),
            'ton' => 'or',
        ];
    }
@endphp

<div class="p-4 sm:p-6 lg:p-8">

    {{-- En-tête de page : le surtitre situe, le titre nomme, la ligne du dessous date. --}}
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <x-surtitre>{{ __("Pilotage") }}</x-surtitre>
            <h1 class="mt-1 text-2xl font-bold text-gray-900 sm:text-3xl">{{ __("Tableau de bord") }}</h1>
            <p class="mt-1 text-sm text-gray-500">
                {{ \Illuminate\Support\Carbon::now()->translatedFormat('l j F Y') }}
            </p>
        </div>

        <div class="flex shrink-0 gap-2">
            <a href="{{ route('investisseurs.index') }}" wire:navigate
               class="inline-flex items-center gap-2 rounded-champ border border-gray-300 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50">
                <x-icone nom="investisseurs" class="h-4 w-4" />
                {{ __("Investisseurs") }}
            </a>

            @if (in_array(auth()->user()->role, ['direction', 'administrateur'], true))
                <a href="{{ route('dividendes.calculer') }}" wire:navigate
                   class="inline-flex items-center gap-2 rounded-champ bg-primaire-700 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-primaire-800">
                    <x-icone nom="dividendes" class="h-4 w-4" />
                    {{ __("Dividendes") }}
                </a>
            @endif
        </div>
    </div>

    @if ($vue === 'gestionnaire' && isset($aucunPortefeuille))
        <div class="rounded-carte border border-or-300 bg-or-50 p-4 text-sm text-or-700">
            {{ __("Votre compte utilisateur n'est relié à aucun profil gestionnaire. Contactez un administrateur.") }}
        </div>

    @else
        {{--
            Bandeau sombre : le seul endroit sombre de la page, et c'est ce qui le fait
            lire en premier. Les chiffres du fonds d'un côté, ce qui attend un geste
            juste en dessous, dans la même carte — on ne les sépare pas.
        --}}
        <div class="mb-6 overflow-hidden rounded-carte bg-nuit-900">
            <div class="flex flex-col gap-6 p-6 lg:flex-row lg:items-center lg:gap-10">
                <div class="lg:w-64 lg:shrink-0">
                    <p class="text-xs font-semibold uppercase tracking-wider text-white/40">{{ __("Aujourd'hui") }}</p>
                    <p class="mt-1 text-lg font-bold text-white">
                        {{ $vue === 'gestionnaire' ? __("Mon portefeuille") : __("Le fonds en un coup d'œil") }}
                    </p>
                    @if ($aTraiter !== [])
                        <p class="mt-1 text-sm text-white/60">{{ __(":nombre point(s) à traiter", ['nombre' => count($aTraiter)]) }}</p>
                    @endif
                </div>

                <div class="grid flex-1 grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4 lg:gap-6 lg:divide-x lg:divide-white/10 rtl:lg:divide-x-reverse">
                    @if ($vue === 'gestionnaire')
                        @php($chiffres = [
                            ['etiquette' => __("Mes investisseurs"), 'valeur' => $nbInvestisseurs, 'accent' => false],
                            ['etiquette' => __("Comptes actifs"), 'valeur' => $nbComptes, 'accent' => false],
                            ['etiquette' => __("Actions détenues"), 'valeur' => \App\Support\Montant::format($totalActions), 'accent' => false],
                            ['etiquette' => __("Solde cumulé"), 'valeur' => \App\Support\Montant::avecDevise($totalSolde), 'accent' => true],
                        ])
                    @else
                        @php($chiffres = [
                            ['etiquette' => __("Investisseurs actifs"), 'valeur' => $nbInvestisseursActifs, 'suffixe' => $nbInvestisseurs > $nbInvestisseursActifs ? __("sur :total", ['total' => $nbInvestisseurs]) : null, 'accent' => false],
                            ['etiquette' => __("Gestionnaires actifs"), 'valeur' => $nbGestionnairesActifs, 'accent' => false],
                            ['etiquette' => __("Dividendes distribués"), 'valeur' => \App\Support\Montant::avecDevise($totalDividendesDistribues), 'accent' => true],
                            ['etiquette' => __("Dividendes ce mois-ci"), 'valeur' => \App\Support\Montant::avecDevise($dividendesCeMois), 'accent' => false],
                        ])
                    @endif

                    @foreach ($chiffres as $i => $chiffre)
                        <div class="{{ $i > 0 ? 'lg:ps-6' : '' }}">
                            <p class="text-xs font-medium uppercase tracking-wide text-white/50">{{ $chiffre['etiquette'] }}</p>
                            <p class="mt-1 text-xl font-bold sm:text-2xl {{ $chiffre['accent'] ? 'text-primaire-400' : 'text-white' }}">
                                {{ $chiffre['valeur'] }}
                                @isset($chiffre['suffixe'])
                                    <span class="text-sm font-normal text-white/50">{{ $chiffre['suffixe'] }}</span>
                                @endisset
                            </p>
                        </div>
                    @endforeach
                </div>
            </div>

            @foreach ($aTraiter as $point)
                <a href="{{ $point['lien'] }}" wire:navigate
                   class="flex items-center gap-4 border-t border-white/10 px-6 py-4 transition hover:bg-white/5">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-champ bg-or-500/15 text-or-300">
                        <x-icone nom="alerte" class="h-5 w-5" />
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-sm font-semibold text-white">
                            {{ $point['nombre'] }} {{ $point['libelle'] }}
                        </span>
                        <span class="block text-xs text-white/50">{{ $point['detail'] }}</span>
                    </span>
                    <svg class="h-5 w-5 shrink-0 text-white/40 rtl:-scale-x-100" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                    </svg>
                </a>
            @endforeach
        </div>

        @if ($vue === 'gestionnaire')
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <x-carte-liste :titre="__('Investisseurs récents')" :lien="route('investisseurs.index')">
                    @forelse ($mesInvestisseurs as $inv)
                        <a href="{{ route('investisseurs.show', $inv) }}" wire:navigate
                           class="-mx-2 flex items-center justify-between rounded-champ px-2 py-2.5 transition hover:bg-gray-50">
                            <span class="text-sm text-gray-800">{{ $inv->nom }} {{ $inv->prenom }}</span>
                            <span class="font-mono text-xs text-gray-400">{{ $inv->identifiant_externe }}</span>
                        </a>
                    @empty
                        <p class="py-2 text-sm text-gray-400">{{ __("Aucun investisseur pour l'instant.") }}</p>
                    @endforelse
                </x-carte-liste>

                <x-carte-liste :titre="__('Derniers achats')">
                    @forelse ($dernierAchats as $achat)
                        <div class="flex items-center justify-between gap-3 py-2.5 text-sm">
                            <span class="font-mono text-xs text-gray-400">{{ $achat->numero_achat }}</span>
                            <span class="text-gray-700">{{ __(":nombre action(s)", ["nombre" => $achat->nombre_actions]) }}</span>
                            <span class="font-semibold text-gray-900">{{ \App\Support\Montant::avecDevise($achat->montant) }}</span>
                        </div>
                    @empty
                        <p class="py-2 text-sm text-gray-400">{{ __("Aucun achat pour l'instant.") }}</p>
                    @endforelse
                </x-carte-liste>
            </div>

        @else
            <div class="mb-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
                <div class="rounded-carte border border-gray-200 bg-white p-6 lg:col-span-2">
                    <h2 class="text-base font-bold text-gray-900">{{ __("Répartition par catégorie") }}</h2>

                    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                        @foreach (['commercial' => __('Commercial'), 'waqf' => __('Waqf')] as $cle => $label)
                            <div class="rounded-champ border border-gray-100 bg-gray-50/60 p-4">
                                <div class="flex items-center justify-between">
                                    <span class="text-sm font-semibold text-gray-800">{{ $label }}</span>
                                    <span class="rounded-full bg-primaire-50 px-2 py-0.5 text-xs font-medium text-primaire-800">
                                        {{ __(":nombre compte(s)", ["nombre" => $comptesParCategorie[$cle] ?? 0]) }}
                                    </span>
                                </div>
                                <p class="mt-3 text-xl font-bold text-gray-900">
                                    {{ \App\Support\Montant::avecDevise($soldeParCategorie[$cle] ?? 0) }}
                                </p>
                                <p class="mt-0.5 text-xs text-gray-500">
                                    {{ __("de solde cumulé") }} ·
                                    {{ __(":nombre action(s)", ["nombre" => \App\Support\Montant::format($actionsNettesParCategorie[$cle] ?? 0)]) }}
                                </p>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Le capital radié non versé est de l'argent qui attend : il porte l'or. --}}
                <div class="rounded-carte border {{ $radiationsEnAttente > 0 ? 'border-or-300 bg-or-50' : 'border-gray-200 bg-white' }} p-6">
                    <h2 class="text-base font-bold text-gray-900">{{ __("Radiations") }}</h2>
                    <p class="mt-4 text-xs font-medium uppercase tracking-wide text-gray-500">{{ __("Capital en attente de versement") }}</p>
                    <p class="mt-1 text-2xl font-bold {{ $radiationsEnAttente > 0 ? 'text-or-700' : 'text-gray-900' }}">
                        {{ \App\Support\Montant::avecDevise($radiationsEnAttente) }}
                    </p>
                    @if ($radiationsEnAttente > 0)
                        <p class="mt-2 text-xs text-or-700/80">{{ __("Consultez les fiches investisseurs concernées pour verser.") }}</p>
                    @endif
                </div>
            </div>

            <x-carte-liste :titre="__('Derniers investisseurs créés')" :lien="route('investisseurs.index')">
                @forelse ($derniersInvestisseurs as $inv)
                    <a href="{{ route('investisseurs.show', $inv) }}" wire:navigate
                       class="-mx-2 flex items-center gap-3 rounded-champ px-2 py-2.5 transition hover:bg-gray-50">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primaire-50 text-xs font-semibold uppercase text-primaire-800">
                            {{ \Illuminate\Support\Str::substr($inv->prenom ?: $inv->nom, 0, 1) }}{{ \Illuminate\Support\Str::substr($inv->nom, 0, 1) }}
                        </span>
                        <span class="min-w-0 flex-1 truncate text-sm text-gray-800">{{ $inv->nom }} {{ $inv->prenom }}</span>
                        <span class="shrink-0 font-mono text-xs text-gray-400">{{ $inv->identifiant_externe }}</span>
                        <span class="hidden shrink-0 text-xs text-gray-400 sm:block">{{ $inv->created_at->diffForHumans() }}</span>
                    </a>
                @empty
                    <p class="py-2 text-sm text-gray-400">{{ __("Aucun investisseur pour l'instant.") }}</p>
                @endforelse
            </x-carte-liste>
        @endif
    @endif
</div>
