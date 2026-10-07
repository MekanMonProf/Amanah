<div>
    <div class="flex flex-col sm:flex-row gap-2 mb-3">
        <input type="text" wire:model.live.debounce.300ms="recherche"
               placeholder="{{ __('Rechercher dans les motifs...') }}"
               class="border rounded px-3 py-1.5 text-sm w-full sm:w-64">

        <select wire:model.live="filtreSens" class="border rounded px-3 py-1.5 text-sm">
            <option value="">{{ __("Donnés et reçus") }}</option>
            <option value="emis">{{ __("Donnés") }}</option>
            <option value="recus">{{ __("Reçus") }}</option>
        </select>

        <input type="date" wire:model.live="dateDebut" class="border rounded px-3 py-1.5 text-sm" title="{{ __("Du") }}">
        <input type="date" wire:model.live="dateFin" class="border rounded px-3 py-1.5 text-sm" title="{{ __("Au") }}">

        @if ($recherche || $filtreSens || $dateDebut || $dateFin)
            <button wire:click="$set('recherche', '')" wire:click.prevent="$set('filtreSens', ''); $set('dateDebut', ''); $set('dateFin', '')"
                    class="text-xs text-gray-500 hover:underline self-center">
                {{ __("Réinitialiser") }}
            </button>
        @endif

        {{-- Les trois autres historiques du compte s'exportaient, pas celui-ci.
             Or un don d'actions n'écrit que sa propre ligne : sans ces deux
             boutons, le nombre d'actions ne se réconciliait pas à partir des
             fichiers d'un compte. --}}
        <div class="flex gap-2 sm:ms-auto">
            @php
                $filtres = [
                    'compte' => $compte->id,
                    'recherche' => $recherche,
                    'sens' => $filtreSens,
                    'date_debut' => $dateDebut,
                    'date_fin' => $dateFin,
                ];
            @endphp
            <a href="{{ route('export.dons.csv', $filtres) }}"
               target="_blank" class="text-xs text-gray-600 border border-gray-300 rounded px-3 py-1.5 hover:bg-gray-50 whitespace-nowrap">
                CSV
            </a>
            <a href="{{ route('export.dons.pdf', $filtres) }}"
               target="_blank" class="text-xs text-gray-600 border border-gray-300 rounded px-3 py-1.5 hover:bg-gray-50 whitespace-nowrap">
                PDF
            </a>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm min-w-[640px]">
            <thead class="text-start text-gray-500">
                <tr>
                    <th class="py-1 cursor-pointer select-none" wire:click="trierPar('date_don')">
                        {{ __("Date") }} {!! $tri === 'date_don' ? ($direction === 'asc' ? '↑' : '↓') : '' !!}
                    </th>
                    <th class="py-1">{{ __("Sens") }}</th>
                    <th class="py-1">{{ __("Contrepartie") }}</th>
                    <th class="py-1 text-end">{{ __("Valeur") }}</th>
                    <th class="py-1">{{ __("Motif") }}</th>
                    <th class="py-1"></th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse ($dons as $don)
                    @php
                        $emis = $don->compte_source_id === $compte->id;
                        $autre = $emis ? $don->compteDestinataire : $don->compteSource;
                    @endphp
                    <tr>
                        <td class="py-1 whitespace-nowrap">{{ $don->date_don->format('d/m/Y') }}</td>
                        <td class="py-1">
                            @if ($emis)
                                <span class="px-2 py-0.5 text-xs rounded-full bg-or-100 text-or-700 whitespace-nowrap">{{ __("Donné") }}</span>
                            @else
                                <span class="px-2 py-0.5 text-xs rounded-full bg-primaire-100 text-primaire-700 whitespace-nowrap">{{ __("Reçu") }}</span>
                            @endif
                            @if ($don->type_operation === 'succession')
                                <span class="ms-1 px-2 py-0.5 text-xs rounded-full bg-gray-100 text-gray-600 whitespace-nowrap">{{ __("Succession") }}</span>
                            @endif
                        </td>
                        <td class="py-1">
                            @if ($autre?->investisseur)
                                <a href="{{ route('investisseurs.show', $autre->investisseur) }}" wire:navigate class="text-primaire-700 hover:underline">
                                    <bdi>{{ $autre->investisseur->nom }} {{ $autre->investisseur->prenom }}</bdi>
                                </a>
                                <span class="block text-xs text-gray-400 font-mono">{{ $autre->numero_compte }}</span>
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="py-1 text-end whitespace-nowrap">
                            @if ($don->type_don === 'actions')
                                {{ __(":nombre action(s)", ["nombre" => \App\Support\Montant::format($don->nombre_actions)]) }}
                            @else
                                {{ \App\Support\Montant::avecDevise($don->montant) }}
                            @endif
                        </td>
                        <td class="py-1 text-gray-500"><bdi>{{ $don->motif ?: '—' }}</bdi></td>
                        <td class="py-1 text-end">
                            @if ($don->piece_justificative_path)
                                <a href="{{ \App\Support\Document::lien($don, 'piece_justificative_path') }}" target="_blank"
                                   class="text-xs text-primaire-700 hover:underline whitespace-nowrap">
                                    {{ __("📄 Pièce") }}
                                </a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-4 text-center text-gray-400">{{ __("Aucun don ne correspond aux critères.") }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-2">{{ $dons->links() }}</div>

    <p class="mt-2 text-xs text-gray-400">
        {{ __("Un don d'actions déplace des titres, pas de l'argent : il ne figure donc pas dans les écritures du compte financier.") }}
    </p>
</div>
