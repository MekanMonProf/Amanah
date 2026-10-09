<div class="p-4 sm:p-6 lg:p-8">
    <x-surtitre>{{ __('Finance') }}</x-surtitre>
    <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 mb-1">{{ __("Calcul des dividendes") }}</h1>
    <p class="text-sm text-gray-500 mb-6">
        {{ __("Fixe le taux de la période choisie (une seule fois, définitif), puis rejoue automatiquement tout l'historique connu pour que chaque compte reçoive tout ce qu'il n'a pas encore perçu.") }}
    </p>

    {{--
        Ce qui a déjà été versé. L'écran ne disait que ce qu'on s'apprête à
        faire ; le taux qu'on saisit se juge pourtant par rapport à ce qui
        précède, et c'est le chiffre qu'on vient chercher quand on le demande.
    --}}
    <div class="mb-6 overflow-hidden rounded-carte bg-nuit-900 shadow-sm">
        <div class="flex flex-col gap-6 p-6 lg:flex-row lg:items-center lg:gap-10">
            <div class="lg:w-56 lg:shrink-0">
                <x-surtitre class="text-white/40">{{ __("Dividendes") }}</x-surtitre>
                <p class="mt-1 text-lg font-bold text-white">{{ __("Ce qui a été distribué") }}</p>
                <p class="mt-1 text-sm text-white/60">
                    @if ($this->position['derniere'])
                        {{ __("Dernière période : :mois", ['mois' => $this->position['derniere']]) }}
                    @else
                        {{ __("Aucun barème fixé pour l'instant.") }}
                    @endif
                </p>
            </div>

            <div class="grid flex-1 grid-cols-1 gap-5 sm:grid-cols-3 lg:gap-6 lg:divide-x lg:divide-white/10 rtl:lg:divide-x-reverse">
                @foreach ([
                    ['etiquette' => __("Périodes fixées"), 'valeur' => \App\Support\Montant::format($this->position['periodes']), 'accent' => false],
                    ['etiquette' => __("Comptes servis"), 'valeur' => \App\Support\Montant::format($this->position['comptes']), 'accent' => false],
                    ['etiquette' => __("Total distribué"), 'valeur' => \App\Support\Montant::avecDevise($this->position['total']), 'accent' => true],
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

    {{-- Le réglage et la saisie restent à largeur de lecture : un champ de
         formulaire étiré sur toute la page ne se remplit pas mieux. --}}
    <div class="max-w-2xl">

    @if (session('succes_parametre'))
        <div class="bg-primaire-50 border border-primaire-200 text-primaire-700 rounded-champ p-3 mb-4 text-sm">
            {{ session('succes_parametre') }}
        </div>
    @endif

    <div class="bg-gray-50 border rounded-champ p-4 mb-6">
        <div class="flex justify-between items-center">
            <div>
                <div class="text-sm font-medium text-gray-700">{{ __("Règle d'éligibilité en fin de mois") }}</div>
                @if (!$modifierDelai)
                    <p class="text-sm text-gray-500 mt-1">
                        @if ($delaiEligibiliteJours === 0)
                            {{ __("Un achat compte pour le dividende du mois en cours, quel que soit le jour du mois.") }}
                        @else
                            {!! __("Un achat effectué dans les <strong>:jours derniers jours</strong> du mois ne compte qu'à partir du mois suivant.", ['jours' => $delaiEligibiliteJours]) !!}
                        @endif
                    </p>
                @endif
            </div>
            <button type="button" wire:click="$toggle('modifierDelai')" class="text-sm text-primaire-700 hover:underline whitespace-nowrap">
                {{ $modifierDelai ? __('Annuler') : __('Modifier') }}
            </button>
        </div>

        @if ($modifierDelai)
            <div class="flex items-end gap-3 mt-3">
                <div>
                    <label class="text-xs text-gray-500">{{ __("Nombre de jours avant la fin du mois") }}</label>
                    <input type="number" min="0" max="30" wire:model="delaiEligibiliteJours" class="border rounded px-3 py-1.5 w-24">
                </div>
                <button type="button" wire:click="enregistrerDelai" class="bg-primaire-700 text-white px-4 py-1.5 rounded-champ text-sm">
                    {{ __("Enregistrer") }}
                </button>
            </div>
            @error('delaiEligibiliteJours') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
        @endif

        <div class="border-t mt-4 pt-4">
            <div class="flex justify-between items-center">
                <div>
                    <div class="text-sm font-medium text-gray-700">{{ __('Règle de sortie en cours de mois') }}</div>
                    @if (!$modifierDelaiRadiation)
                        <p class="text-sm text-gray-500 mt-1">
                            @if ($delaiRadiationJours === 0)
                                {{ __("Une radiation prend effet immédiatement : les actions radiées ne touchent pas le dividende du mois.") }}
                            @elseif ($delaiRadiationJours >= 31)
                                {{ __("Une radiation effectuée en cours de mois, quel que soit le jour, laisse les actions toucher le dividende de ce mois.") }}
                            @else
                                {!! __("Une radiation effectuée dans les <strong>:jours derniers jours</strong> du mois laisse les actions toucher le dividende de ce mois.", ['jours' => $delaiRadiationJours]) !!}
                            @endif
                        </p>
                    @endif
                </div>
                <button type="button" wire:click="$toggle('modifierDelaiRadiation')" class="text-sm text-primaire-700 hover:underline whitespace-nowrap">
                    {{ $modifierDelaiRadiation ? __('Annuler') : __('Modifier') }}
                </button>
            </div>

            @if ($modifierDelaiRadiation)
                <div class="flex items-end gap-3 mt-3">
                    <div>
                        <label class="text-xs text-gray-500">{{ __('Nombre de jours avant la fin du mois') }}</label>
                        <input type="number" min="0" max="31" wire:model="delaiRadiationJours" class="border rounded px-3 py-1.5 w-24">
                    </div>
                    <button type="button" wire:click="enregistrerDelaiRadiation" class="bg-primaire-700 text-white px-4 py-1.5 rounded-champ text-sm">
                        {{ __('Enregistrer') }}
                    </button>
                </div>
                <p class="text-xs text-gray-500 mt-2">{{ __("0 = effet immédiat. 31 = le mois entier, quel que soit le jour de la radiation.") }}</p>
                @error('delaiRadiationJours') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
            @endif
        </div>
    </div>

    <form wire:submit="calculerEtDistribuer" class="bg-white border rounded-carte p-5 space-y-4">
        <div>
            <label class="text-sm text-gray-600">{{ __("Période (mois concerné)") }}</label>
            <input type="month" wire:model.live="periode"
                   value="{{ \Illuminate\Support\Carbon::parse($periode)->format('Y-m') }}"
                   class="w-full border rounded px-3 py-2">
            @error('periode') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="text-sm text-gray-600">{{ __("Bénéfice par action — Commercial (CFA)") }}</label>
                <input type="number" step="0.01" wire:model="benefice_commercial"
                       {{ $baremeCommercialExistant ? 'disabled' : '' }}
                       class="w-full border rounded px-3 py-2 {{ $baremeCommercialExistant ? 'bg-gray-50 text-gray-500' : '' }}">
                @error('benefice_commercial') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                @if ($baremeCommercialExistant)
                    <p class="text-xs text-or-700 mt-1">{{ __("🔒 Déjà fixé pour cette période — non modifiable.") }}</p>
                @endif
            </div>
            <div>
                <label class="text-sm text-gray-600">{{ __("Bénéfice par action — Waqf (CFA)") }}</label>
                <input type="number" step="0.01" wire:model="benefice_waqf"
                       {{ $baremeWaqfExistant ? 'disabled' : '' }}
                       class="w-full border rounded px-3 py-2 {{ $baremeWaqfExistant ? 'bg-gray-50 text-gray-500' : '' }}">
                @error('benefice_waqf') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                @if ($baremeWaqfExistant)
                    <p class="text-xs text-or-700 mt-1">{{ __("🔒 Déjà fixé pour cette période — non modifiable.") }}</p>
                @endif
            </div>
        </div>

        <div class="bg-gray-50 border border-gray-200 rounded-champ p-3 text-sm text-gray-600">
            {!! __("Chaque lancement rejoue automatiquement <strong>tout l'historique</strong> des barèmes déjà fixés, pas seulement cette période. Un compte ouvert après coup rattrape ainsi tous les mois qu'il a manqués, avec le taux qui était en vigueur à chaque période.") !!}
        </div>

        <button type="submit" wire:loading.attr="disabled" wire:target="calculerEtDistribuer"
                class="bg-primaire-700 text-white px-5 py-2 rounded-champ w-full sm:w-auto disabled:opacity-50 disabled:cursor-not-allowed">
            <span wire:loading.remove wire:target="calculerEtDistribuer">{{ __("Calculer et distribuer") }}</span>
            <span wire:loading wire:target="calculerEtDistribuer">{{ __("Calcul en cours...") }}</span>
        </button>
    </form>

    @if ($resultats)
        <div class="mt-6 space-y-4">
            <h2 class="font-semibold text-gray-800">{{ __("Résultat de ce passage (toutes périodes confondues)") }}</h2>

            @foreach (['commercial' => 'Commercial', 'waqf' => 'Waqf'] as $cle => $label)
                <div class="bg-white border rounded-carte p-4">
                    <div class="flex justify-between items-center mb-2">
                        <span class="px-2 py-1 text-xs font-semibold rounded {{ $cle === 'commercial' ? 'bg-primaire-100 text-primaire-800' : 'bg-nuit-900 text-primaire-100' }}">
                            {{ $label }}
                        </span>
                    </div>
                    <div class="grid grid-cols-3 gap-3 text-sm">
                        <div>
                            <div class="text-xs text-gray-500">{{ __("Comptes crédités") }}</div>
                            <div class="text-lg font-semibold text-gray-800">{{ $resultats[$cle]['nb_comptes'] }}</div>
                        </div>
                        <div>
                            <div class="text-xs text-gray-500">{{ __("Déjà à jour (ignorés)") }}</div>
                            <div class="text-lg font-semibold text-gray-400">{{ $resultats[$cle]['deja_traites'] }}</div>
                        </div>
                        <div>
                            <div class="text-xs text-gray-500">{{ __("Total distribué") }}</div>
                            <div class="text-lg font-semibold text-primaire-700">{{ \App\Support\Montant::format($resultats[$cle]['total_distribue']) }}&#8239;CFA</div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    </div>{{-- fin de la largeur de lecture : le tableau, lui, prend la page --}}

    @if (!empty($baremesHistorique))
        <div class="mt-8">
            <h2 class="font-semibold text-gray-800 mb-3">{{ __("Historique des barèmes fixés") }}</h2>

            <div class="overflow-hidden rounded-carte border border-gray-200 bg-white">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[560px] text-sm">
                        <thead class="bg-gray-50 text-start text-xs uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-4 py-3 text-start font-semibold">{{ __("Période") }}</th>
                                <th class="px-4 py-3 text-end font-semibold">{{ __("Commercial") }}</th>
                                <th class="px-4 py-3 text-end font-semibold">{{ __("Waqf") }}</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>

                        {{-- Un <tbody> par année, comme sur les relevés : au bout de deux
                             ans la liste défile longtemps avant le mois qu'on cherche.
                             Les clés sont déjà rangées du plus récent au plus ancien. --}}
                        {{-- preserveKeys : sans lui, groupBy renumérote, et la clé
                             « 2026-03 » dont on tire le mois devient un simple 0. --}}
                        @foreach (collect($baremesHistorique)->groupBy(fn ($lignes, $mois) => substr($mois, 0, 4), preserveKeys: true) as $annee => $moisDeLAnnee)
                            <tbody x-data="{ ouvert: true }" class="divide-y divide-gray-100">
                                <x-entete-groupe :titre="$annee"
                                                 :detail="__(':nombre période(s)', ['nombre' => $moisDeLAnnee->count()])" />

                                @foreach ($moisDeLAnnee as $mois => $parCategorie)
                                    <tr x-show="ouvert" class="hover:bg-gray-50">
                                        <td class="whitespace-nowrap px-4 py-3 font-semibold text-gray-900">
                                            {{ \Illuminate\Support\Str::ucfirst(\Illuminate\Support\Carbon::createFromFormat('Y-m', $mois)->translatedFormat('F Y')) }}
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-3 text-end text-gray-600">
                                            <bdi>{{ isset($parCategorie['commercial']) ? \App\Support\Montant::avecDevise($parCategorie['commercial']['benefice_par_action'], 2) : '—' }}</bdi>
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-3 text-end text-gray-600">
                                            <bdi>{{ isset($parCategorie['waqf']) ? \App\Support\Montant::avecDevise($parCategorie['waqf']['benefice_par_action'], 2) : '—' }}</bdi>
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-3 text-end">
                                            {{-- Le rouge n'arrive qu'au survol : corriger un barème
                                                 réécrit une distribution déjà versée, mais l'action
                                                 n'a pas à crier sur chaque ligne. --}}
                                            <div class="flex items-center justify-end gap-3">
                                                @if (isset($parCategorie['commercial']))
                                                    <a href="{{ route('baremes.corriger', $parCategorie['commercial']['id']) }}" wire:navigate
                                                       class="text-xs text-gray-500 hover:text-red-700">{{ __("Corriger Com.") }}</a>
                                                @endif
                                                @if (isset($parCategorie['waqf']))
                                                    <a href="{{ route('baremes.corriger', $parCategorie['waqf']['id']) }}" wire:navigate
                                                       class="text-xs text-gray-500 hover:text-red-700">{{ __("Corriger Waqf") }}</a>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        @endforeach
                    </table>
                </div>
            </div>
        </div>
    @endif
</div>
