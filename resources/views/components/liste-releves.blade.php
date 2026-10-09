@props([
    // Les mois, tels que App\Support\PeriodesReleve les décrit.
    'periodes',
    // La route qui fabrique le PDF : celle du portail pour l'investisseur,
    // celle de la fiche pour le gestionnaire. Les deux prennent les mêmes
    // bornes de dates ; seule la façon de désigner le dossier diffère.
    'route',
    // Ce qu'il faut ajouter pour désigner le dossier, le cas échéant.
    'parametres' => [],
])

@php
    $lien = fn (array $periode, array $extra = []) => route($route, array_merge(
        $parametres,
        ['date_debut' => $periode['debut'], 'date_fin' => $periode['fin']],
        $extra,
    ));
@endphp

<div class="overflow-x-auto">
    <table class="w-full min-w-[520px] text-sm">
        <thead class="bg-gray-50 text-start text-xs uppercase tracking-wide text-gray-500">
            <tr>
                <th class="px-4 py-3 text-start font-semibold">{{ __("Période") }}</th>
                <th class="px-4 py-3 text-start font-semibold">{{ __("Relevé") }}</th>
                <th class="px-4 py-3 text-start font-semibold">{{ __("Mouvements") }}</th>
                {{-- Sans intitulé : dans AMANAH, « Actions » désigne les parts
                     sociales — l'anglais le traduit par « Shares ». --}}
                <th class="px-4 py-3"></th>
            </tr>
        </thead>

        {{-- Un <tbody> par année : un tableau en accepte plusieurs, et c'est ce
             qui donne à chaque groupe son propre état de repli. --}}
        @foreach ($periodes->groupBy('annee') as $annee => $mois)
            <tbody x-data="{ ouvert: true }" class="divide-y divide-gray-100">
                {{-- L'année sépare, comme sur l'ancien écran : on cherche « le relevé
                     d'août dernier », pas la quatorzième ligne. --}}
                <x-entete-groupe :titre="$annee"
                                 :detail="__(':nombre relevé(s)', ['nombre' => $mois->count()])" />

                @foreach ($mois as $periode)
                    <tr x-show="ouvert" class="hover:bg-gray-50">
                        <td class="whitespace-nowrap px-4 py-3 font-semibold text-gray-900">
                            {{ $periode['libelle'] }}
                        </td>
                        <td class="px-4 py-3 font-mono text-xs text-gray-500">
                            {{ $periode['fichier'] }}
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-gray-600">
                            {{ $periode['mouvements'] }}
                        </td>
                        <td class="whitespace-nowrap px-4 py-3">
                            {{-- Même sobriété que les actions de ligne ailleurs :
                                 une icône, un mot dès qu'il y a la place. --}}
                            <div class="flex items-center justify-end gap-4">
                                <a href="{{ $lien($periode) }}" target="_blank"
                                   title="{{ __('Ouvrir') }}"
                                   class="inline-flex items-center gap-1.5 text-sm text-gray-600 hover:text-primaire-700">
                                    <x-icone nom="oeil" class="h-4 w-4 shrink-0" />
                                    <span class="hidden xl:inline">{{ __("Ouvrir") }}</span>
                                </a>
                                <a href="{{ $lien($periode, ['telecharger' => 1]) }}"
                                   title="{{ __('Télécharger') }}"
                                   class="inline-flex items-center gap-1.5 text-sm text-gray-600 hover:text-primaire-700">
                                    <x-icone nom="telecharger" class="h-4 w-4 shrink-0" />
                                    <span class="hidden xl:inline">{{ __("Télécharger") }}</span>
                                </a>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        @endforeach
    </table>
</div>
