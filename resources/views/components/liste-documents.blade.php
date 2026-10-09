@props([
    // Les documents, tels que App\Support\DocumentsDuDossier les décrit.
    'documents',
])

@php
    // Chaque document porte sa propre route et ses paramètres : les attestations
    // vérifient elles-mêmes le droit d'y accéder, et répondent donc à
    // l'investisseur comme au gestionnaire sans qu'on ait à les distinguer ici.
    $lien = fn (array $document, array $extra = []) => route(
        $document['route'],
        array_merge($document['parametres'], $extra),
    );
@endphp

<div class="overflow-x-auto">
    <table class="w-full min-w-[560px] text-sm">
        <thead class="bg-gray-50 text-start text-xs uppercase tracking-wide text-gray-500">
            <tr>
                <th class="px-4 py-3 text-start font-semibold">{{ __("Date") }}</th>
                <th class="px-4 py-3 text-start font-semibold">{{ __("Document") }}</th>
                <th class="px-4 py-3 text-start font-semibold">{{ __("Objet") }}</th>
                {{-- Sans intitulé : dans AMANAH, « Actions » désigne les parts
                     sociales — l'anglais le traduit par « Shares ». --}}
                <th class="px-4 py-3"></th>
            </tr>
        </thead>

        <tbody class="divide-y divide-gray-100">
            @foreach ($documents->groupBy('famille') as $famille => $lot)
                {{-- La famille sépare, là où le relevé sépare par année : on vient
                     chercher « mon attestation d'achat », pas le document n° 14. --}}
                <tr class="bg-primaire-50/60">
                    <td colspan="4" class="px-4 py-2 text-sm text-primaire-800">
                        <span class="font-bold">
                            {{ __(\App\Support\DocumentsDuDossier::FAMILLES[$famille] ?? $famille) }}
                        </span>
                        <span class="text-primaire-700/70">
                            — {{ __(":nombre document(s)", ['nombre' => $lot->count()]) }}
                        </span>
                    </td>
                </tr>

                @foreach ($lot as $document)
                    <tr class="hover:bg-gray-50">
                        <td class="whitespace-nowrap px-4 py-3 font-semibold text-gray-900">
                            {{ $document['date']?->format('d/m/Y') }}
                        </td>
                        <td class="px-4 py-3 font-mono text-xs text-gray-500">
                            {{ $document['fichier'] }}
                        </td>
                        <td class="px-4 py-3 text-gray-600">
                            {{-- <bdi> isole le montant du sens d'écriture : en arabe,
                                 « 100 000 CFA » se réordonnerait en « CFA 000 100 ». --}}
                            <bdi>{{ $document['objet'] }}</bdi>
                        </td>
                        <td class="whitespace-nowrap px-4 py-3">
                            <div class="flex items-center justify-end gap-4">
                                <a href="{{ $lien($document) }}" target="_blank"
                                   title="{{ __('Ouvrir') }}"
                                   class="inline-flex items-center gap-1.5 text-sm text-gray-600 hover:text-primaire-700">
                                    <x-icone nom="oeil" class="h-4 w-4 shrink-0" />
                                    <span class="hidden xl:inline">{{ __("Ouvrir") }}</span>
                                </a>
                                <a href="{{ $lien($document, ['telecharger' => 1]) }}"
                                   title="{{ __('Télécharger') }}"
                                   class="inline-flex items-center gap-1.5 text-sm text-gray-600 hover:text-primaire-700">
                                    <x-icone nom="telecharger" class="h-4 w-4 shrink-0" />
                                    <span class="hidden xl:inline">{{ __("Télécharger") }}</span>
                                </a>
                            </div>
                        </td>
                    </tr>
                @endforeach
            @endforeach
        </tbody>
    </table>
</div>
