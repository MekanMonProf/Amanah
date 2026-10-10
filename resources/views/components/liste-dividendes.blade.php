@props([
    // L'historique, tel que App\Support\HistoriqueDividendes le décrit.
    'historique',
    // La colonne du compte ne se justifie que si le dossier en porte plusieurs :
    // commercial et waqf suivent des barèmes distincts, et c'est ce qui explique
    // deux taux le même mois.
    'plusieursComptes' => false,
])

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

        {{-- Un <tbody> par année, repliable comme sur les relevés : au bout de
             deux ans la liste défile longtemps avant le mois qu'on cherche. --}}
        @foreach ($historique->groupBy('annee') as $annee => $mois)
            <tbody x-data="{ ouvert: true }" class="divide-y divide-gray-100">
                <x-entete-groupe :titre="$annee"
                                 :colonnes="$plusieursComptes ? 5 : 4"
                                 :detail="\App\Support\Montant::avecDevise($mois->sum('montant'))" />

                @foreach ($mois as $periode)
                    @foreach ($periode['lignes'] as $rang => $ligne)
                        <tr x-show="ouvert" class="hover:bg-gray-50">
                            {{-- Le mois ne s'écrit qu'une fois, même quand deux comptes
                                 l'ont touché : le répéter le ferait lire comme deux
                                 périodes distinctes. --}}
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
