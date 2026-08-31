<div>
    <div class="flex flex-col sm:flex-row gap-2 mb-3">
        <input type="text" wire:model.live.debounce.300ms="recherche"
               placeholder="Rechercher dans les observations..."
               class="border rounded px-3 py-1.5 text-sm w-full sm:w-64">

        <select wire:model.live="filtreType" class="border rounded px-3 py-1.5 text-sm">
            <option value="">Tous les types</option>
            <option value="dividende">Dividende</option>
            <option value="versement_complementaire">Versement complémentaire</option>
            <option value="achat_action">Achat d'action</option>
            <option value="paiement">Paiement</option>
            <option value="radiation">Radiation</option>
            <option value="ajustement">Ajustement</option>
        </select>

        <input type="date" wire:model.live="dateDebut" class="border rounded px-3 py-1.5 text-sm" title="Du">
        <input type="date" wire:model.live="dateFin" class="border rounded px-3 py-1.5 text-sm" title="Au">

        @if ($recherche || $filtreType || $dateDebut || $dateFin)
            <button wire:click="$set('recherche', '')" wire:click.prevent="$set('filtreType', ''); $set('dateDebut', ''); $set('dateFin', '')"
                    class="text-xs text-gray-500 hover:underline self-center">
                Réinitialiser
            </button>
        @endif

        <div class="flex gap-2 sm:ml-auto">
            <a href="{{ route('export.ecritures.csv', ['compte' => $compte->id, 'recherche' => $recherche, 'type' => $filtreType, 'date_debut' => $dateDebut, 'date_fin' => $dateFin]) }}"
               target="_blank" class="text-xs text-gray-600 border border-gray-300 rounded px-3 py-1.5 hover:bg-gray-50 whitespace-nowrap">
                CSV
            </a>
            <a href="{{ route('export.ecritures.pdf', ['compte' => $compte->id, 'recherche' => $recherche, 'type' => $filtreType, 'date_debut' => $dateDebut, 'date_fin' => $dateFin]) }}"
               target="_blank" class="text-xs text-gray-600 border border-gray-300 rounded px-3 py-1.5 hover:bg-gray-50 whitespace-nowrap">
                PDF
            </a>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm min-w-[480px]">
            <thead class="text-left text-gray-500">
                <tr>
                    <th class="py-1 w-10" title="Ordre réel d'enregistrement — toujours fiable, quel que soit le tri utilisé">#</th>
                    <th class="py-1 cursor-pointer select-none" wire:click="trierPar('date_ecriture')" title="Date à laquelle se rapporte l'opération — peut différer de l'ordre réel d'enregistrement">
                        Date effective {!! $tri === 'date_ecriture' ? ($direction === 'asc' ? '↑' : '↓') : '' !!}
                    </th>
                    <th class="py-1 cursor-pointer select-none" wire:click="trierPar('type_ecriture')">
                        Type {!! $tri === 'type_ecriture' ? ($direction === 'asc' ? '↑' : '↓') : '' !!}
                    </th>
                    <th class="py-1 text-right cursor-pointer select-none" wire:click="trierPar('montant')">
                        Montant {!! $tri === 'montant' ? ($direction === 'asc' ? '↑' : '↓') : '' !!}
                    </th>
                    <th class="py-1 text-right">Solde après</th>
                    <th class="py-1"></th>
                    <th class="py-1"></th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse ($ecritures as $ecriture)
                    <tr>
                        <td class="py-1 text-xs text-gray-400 font-mono">{{ $rangs[$ecriture->id] ?? '—' }}</td>
                        <td class="py-1">{{ $ecriture->date_ecriture->format('d/m/Y') }}</td>
                        <td class="py-1">
                            {{ str_replace('_', ' ', $ecriture->type_ecriture) }}
                            @if ($ecriture->observations)
                                <div class="text-xs text-gray-400">{{ $ecriture->observations }}</div>
                            @endif
                        </td>                        
                        <td class="py-1 text-right {{ $ecriture->montant >= 0 ? 'text-emerald-700' : 'text-red-600' }}">
                            {{ $ecriture->montant >= 0 ? '+' : '' }}{{ number_format($ecriture->montant, 0, ',', ' ') }}
                        </td>
                        <td class="py-1 text-right text-gray-500">{{ number_format($ecriture->solde_apres, 0, ',', ' ') }}</td>
                        <td class="py-1 text-right">
                            @if ($ecriture->piece_justificative_path)
                                <a href="{{ \Illuminate\Support\Facades\Storage::url($ecriture->piece_justificative_path) }}" target="_blank" class="text-emerald-700 hover:underline text-xs">
                                    Justificatif →
                                </a>
                            @endif
                        </td>
                        <td class="py-1 text-right">
                           @if ($ecriture->type_ecriture === 'paiement' && $ecriture->reference_type === 'succession_deces')
                                <a href="{{ route('ecritures.attestation.deces', $ecriture) }}" target="_blank" class="text-gray-600 hover:underline text-xs whitespace-nowrap ml-2">
                                    📄 Attestation (Décès)
                                </a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="py-4 text-center text-gray-400">Aucune écriture ne correspond aux critères.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-2">{{ $ecritures->links() }}</div>

    @if ($tri !== 'id')
        <p class="text-xs text-gray-400 mt-2">
            Tri actuel : {{ $tri === 'date_ecriture' ? 'date effective' : $tri }}. La colonne <strong>#</strong> à
            gauche montre toujours l'ordre réel d'enregistrement, sur lequel le solde est calculé — fiez-vous à elle,
            pas à l'ordre des lignes, pour suivre la progression du solde.
        </p>
    @endif
</div>
