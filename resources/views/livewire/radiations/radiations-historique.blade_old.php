<div>
    <div class="flex flex-col sm:flex-row gap-2 mb-3">
        <input type="text" wire:model.live.debounce.300ms="recherche"
               placeholder="Rechercher (n°, référence)..."
               class="border rounded px-3 py-1.5 text-sm w-full sm:w-64">
        <input type="date" wire:model.live="dateDebut" class="border rounded px-3 py-1.5 text-sm" title="Du">
        <input type="date" wire:model.live="dateFin" class="border rounded px-3 py-1.5 text-sm" title="Au">

        @if ($recherche || $dateDebut || $dateFin)
            <button wire:click="$set('recherche', '')" wire:click.prevent="$set('dateDebut', ''); $set('dateFin', '')"
                    class="text-xs text-gray-500 hover:underline self-center">
                Réinitialiser
            </button>
        @endif
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm min-w-[640px]">
            <thead class="text-left text-gray-500">
                <tr>
                    <th class="py-1 cursor-pointer select-none" wire:click="trierPar('numero_radiation')">
                        N° {!! $tri === 'numero_radiation' ? ($direction === 'asc' ? '↑' : '↓') : '' !!}
                    </th>
                    <th class="py-1 cursor-pointer select-none" wire:click="trierPar('date_radiation')">
                        Date {!! $tri === 'date_radiation' ? ($direction === 'asc' ? '↑' : '↓') : '' !!}
                    </th>
                    <th class="py-1 text-right cursor-pointer select-none" wire:click="trierPar('nombre_actions_radiees')">
                        Actions {!! $tri === 'nombre_actions_radiees' ? ($direction === 'asc' ? '↑' : '↓') : '' !!}
                    </th>
                    <th class="py-1 text-right cursor-pointer select-none" wire:click="trierPar('montant_total')">
                        Montant {!! $tri === 'montant_total' ? ($direction === 'asc' ? '↑' : '↓') : '' !!}
                    </th>
                    <th class="py-1">Statut</th>
                    <th class="py-1"></th>
                    <th class="py-1"></th>
                    <th class="py-1"></th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse ($radiations as $radiation)
                    @php
                        $verse = (float) ($montantsVerses[$radiation->id] ?? 0);
                        $restant = $radiation->montant_total - $verse;
                    @endphp
                    <tr>
                        <td class="py-1 font-mono text-xs">{{ $radiation->numero_radiation }}</td>
                        <td class="py-1">{{ $radiation->date_radiation->format('d/m/Y') }}</td>
                        <td class="py-1 text-right">{{ number_format($radiation->nombre_actions_radiees, 0, ',', ' ') }}</td>
                        <td class="py-1 text-right">{{ number_format($radiation->montant_total, 0, ',', ' ') }}</td>
                        <td class="py-1">
                            @if ($restant <= 0)
                                <span class="px-2 py-0.5 text-xs rounded-full bg-emerald-100 text-emerald-700">Payé</span>
                            @elseif ($verse > 0)
                                <span class="px-2 py-0.5 text-xs rounded-full bg-amber-100 text-amber-700" title="Reste {{ number_format($restant, 0, ',', ' ') }} CFA">
                                    Partiel
                                </span>
                            @else
                                <span class="px-2 py-0.5 text-xs rounded-full bg-gray-100 text-gray-600">Non payé</span>
                            @endif
                        </td>
                        <td class="py-1 text-right">
                            @if ($radiation->piece_justificative_path)
                                <a href="{{ \Illuminate\Support\Facades\Storage::url($radiation->piece_justificative_path) }}" target="_blank" class="text-emerald-700 hover:underline text-xs">
                                    Pièce →
                                </a>
                            @endif
                        </td>
                        <td class="py-1 text-right">
                            @if ($restant > 0 && $compte->politique()?->versement_capital_radiation_possible)
                                <a href="{{ route('comptes.paiement', $compte) }}?source=radiation&radiation_id={{ $radiation->id }}&montant={{ $restant }}&reference={{ $radiation->numero_radiation }}"
                                   wire:navigate class="text-teal-700 hover:underline text-xs whitespace-nowrap">
                                    Verser →
                                </a>
                            @endif
                        </td>
                        <td class="py-1 text-right">
                            <a href="{{ route('radiations.attestation', $radiation) }}" target="_blank" class="text-gray-600 hover:underline text-xs whitespace-nowrap">
                                📄 Attestation
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="py-4 text-center text-gray-400">Aucune radiation enregistrée.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-2">{{ $radiations->links() }}</div>
</div>
