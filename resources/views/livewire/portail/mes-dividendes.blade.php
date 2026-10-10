<div class="p-4 sm:p-6 lg:p-8">
    @if (! $investisseur)
        <div class="rounded-champ border border-or-300 bg-or-50 p-4 text-sm text-or-700">
            {{ __("Votre compte n'est relié à aucun dossier investisseur. Contactez votre gestionnaire.") }}
        </div>
    @else
        <x-surtitre>{{ __('Mon espace') }}</x-surtitre>
        <h1 class="mb-1 text-2xl font-bold text-gray-900 sm:text-3xl">{{ __("Mes dividendes") }}</h1>
        <p class="mb-6 font-mono text-sm text-gray-500">{{ $investisseur->identifiant_externe }}</p>

        @if ($historique->isEmpty())
            <div class="rounded-carte border bg-white p-8 text-center text-gray-400">
                {{ __("Aucun dividende versé pour l'instant. Le premier arrivera à la prochaine distribution.") }}
            </div>
        @else
            {{-- Ce que les mois additionnés donnent : c'est la question qu'on se
                 pose en arrivant, avant de lire le détail ligne à ligne. --}}
            <div class="mb-6 overflow-hidden rounded-carte bg-nuit-900 shadow-sm">
                <div class="flex flex-col gap-6 p-6 lg:flex-row lg:items-center lg:gap-10">
                    <div class="lg:w-56 lg:shrink-0">
                        <x-surtitre class="text-white/40">{{ __("Dividendes") }}</x-surtitre>
                        <p class="mt-1 text-lg font-bold text-white">{{ __("Ce que j'ai perçu") }}</p>
                        <p class="mt-1 text-sm text-white/60">
                            {{ __(":nombre mois servi(s)", ['nombre' => \App\Support\Montant::format($cumul['mois'])]) }}
                        </p>
                    </div>

                    <div class="grid flex-1 grid-cols-1 gap-5 sm:grid-cols-3 lg:gap-6 lg:divide-x lg:divide-white/10 rtl:lg:divide-x-reverse">
                        @foreach ([
                            ['etiquette' => __("Dernier mois"), 'valeur' => $cumul['dernierePeriode'] ?? '—', 'accent' => false],
                            ['etiquette' => __("Dernier versement"), 'valeur' => \App\Support\Montant::avecDevise($cumul['dernier'] ?? 0), 'accent' => false],
                            ['etiquette' => __("Total perçu"), 'valeur' => \App\Support\Montant::avecDevise($cumul['total']), 'accent' => true],
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

            <div class="overflow-hidden rounded-carte border border-gray-200 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[560px] text-sm">
                        <thead class="bg-gray-50 text-start text-xs uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-4 py-3 text-start font-semibold">{{ __("Période") }}</th>
                                @if ($plusieursComptes)
                                    <th class="px-4 py-3 text-start font-semibold">{{ __("Compte") }}</th>
                                @endif
                                <th class="px-4 py-3 text-end font-semibold">{{ __("Actions détenues") }}</th>
                                <th class="px-4 py-3 text-end font-semibold">{{ __("Bénéfice par action") }}</th>
                                <th class="px-4 py-3 text-end font-semibold">{{ __("Montant crédité") }}</th>
                            </tr>
                        </thead>

                        {{-- Un <tbody> par année, repliable comme sur les relevés : au
                             bout de deux ans la liste défile longtemps. --}}
                        @foreach ($historique->groupBy('annee') as $annee => $mois)
                            <tbody x-data="{ ouvert: true }" class="divide-y divide-gray-100">
                                <x-entete-groupe :titre="$annee"
                                                 :colonnes="$plusieursComptes ? 5 : 4"
                                                 :detail="\App\Support\Montant::avecDevise($mois->sum('montant'))" />

                                @foreach ($mois as $periode)
                                    @foreach ($periode['lignes'] as $rang => $ligne)
                                        <tr x-show="ouvert" class="hover:bg-gray-50">
                                            {{-- Le mois ne s'écrit qu'une fois, même quand deux
                                                 comptes l'ont touché : le répéter le ferait lire
                                                 comme deux périodes distinctes. --}}
                                            <td class="whitespace-nowrap px-4 py-3 font-semibold text-gray-900">
                                                {{ $rang === 0 ? $periode['libelle'] : '' }}
                                            </td>
                                            @if ($plusieursComptes)
                                                <td class="whitespace-nowrap px-4 py-3 text-gray-600">
                                                    {{ __(\App\Support\Libelles::categorie($ligne['compte']?->categorie)) }}
                                                </td>
                                            @endif
                                            <td class="whitespace-nowrap px-4 py-3 text-end text-gray-600">
                                                {{ \App\Support\Montant::format($ligne['actions']) }}
                                            </td>
                                            <td class="whitespace-nowrap px-4 py-3 text-end text-gray-600">
                                                <bdi>{{ \App\Support\Montant::avecDevise($ligne['taux'], 2) }}</bdi>
                                            </td>
                                            <td class="whitespace-nowrap px-4 py-3 text-end font-semibold text-primaire-700">
                                                <bdi>{{ \App\Support\Montant::avecDevise($ligne['montant']) }}</bdi>
                                            </td>
                                        </tr>
                                    @endforeach
                                @endforeach
                            </tbody>
                        @endforeach
                    </table>
                </div>
            </div>

            <p class="mt-3 text-xs text-gray-400">
                {{ __("Le bénéfice par action est fixé chaque mois pour l'ensemble des actionnaires ; votre montant dépend du nombre d'actions que vous déteniez ce mois-là.") }}
            </p>
        @endif
    @endif
</div>
