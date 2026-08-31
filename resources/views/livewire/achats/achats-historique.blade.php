<div>
    <div class="flex flex-col sm:flex-row gap-2 mb-3">
        <input type="text" wire:model.live.debounce.300ms="recherche"
               placeholder="Rechercher (n°, référence, paiement)..."
               class="border rounded px-3 py-1.5 text-sm w-full sm:w-64">

        <select wire:model.live="filtreType" class="border rounded px-3 py-1.5 text-sm">
            <option value="">Tous les types</option>
            <option value="initial">Initial</option>
            <option value="rajout">Rajout</option>
            <option value="complement">Complément</option>
            <option value="benefice">Bénéfice</option>
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
            <a href="{{ route('export.achats.csv', ['compte' => $compte->id, 'recherche' => $recherche, 'type' => $filtreType, 'date_debut' => $dateDebut, 'date_fin' => $dateFin]) }}"
               target="_blank" class="text-xs text-gray-600 border border-gray-300 rounded px-3 py-1.5 hover:bg-gray-50 whitespace-nowrap">
                CSV
            </a>
            <a href="{{ route('export.achats.pdf', ['compte' => $compte->id, 'recherche' => $recherche, 'type' => $filtreType, 'date_debut' => $dateDebut, 'date_fin' => $dateFin]) }}"
               target="_blank" class="text-xs text-gray-600 border border-gray-300 rounded px-3 py-1.5 hover:bg-gray-50 whitespace-nowrap">
                PDF
            </a>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm min-w-[560px]">
            <thead class="text-left text-gray-500">
                <tr>
                    <th class="py-1 cursor-pointer select-none" wire:click="trierPar('numero_achat')">
                        N° {!! $tri === 'numero_achat' ? ($direction === 'asc' ? '↑' : '↓') : '' !!}
                    </th>
                    <th class="py-1 cursor-pointer select-none" wire:click="trierPar('date_achat')">
                        Date {!! $tri === 'date_achat' ? ($direction === 'asc' ? '↑' : '↓') : '' !!}
                    </th>
                    <th class="py-1 cursor-pointer select-none" wire:click="trierPar('type_achat')">
                        Type {!! $tri === 'type_achat' ? ($direction === 'asc' ? '↑' : '↓') : '' !!}
                    </th>
                    <th class="py-1 text-right cursor-pointer select-none" wire:click="trierPar('nombre_actions')">
                        Actions {!! $tri === 'nombre_actions' ? ($direction === 'asc' ? '↑' : '↓') : '' !!}
                    </th>
                    <th class="py-1 text-right cursor-pointer select-none" wire:click="trierPar('montant')">
                        Montant {!! $tri === 'montant' ? ($direction === 'asc' ? '↑' : '↓') : '' !!}
                    </th>
                    <th class="py-1">Paiement</th>
                    <th class="py-1"></th>
                    <th class="py-1"></th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse ($achats as $achat)
                    <tr>
                        <td class="py-1 font-mono text-xs">{{ $achat->numero_achat }}</td>
                        <td class="py-1">{{ $achat->date_achat->format('d/m/Y') }}</td>
                        <td class="py-1">{{ ucfirst($achat->type_achat) }}</td>
                        <td class="py-1 text-right">{{ number_format($achat->nombre_actions, 0, ',', ' ') }}</td>
                        <td class="py-1 text-right">{{ number_format($achat->montant, 0, ',', ' ') }}</td>
                        <td class="py-1 text-xs text-gray-500">{{ $achat->mode_paiement ?: '—' }}</td>
                        <td class="py-1 text-right">
                            @if ($achat->photo_facture_path)
                                <a href="{{ \Illuminate\Support\Facades\Storage::url($achat->photo_facture_path) }}" target="_blank" class="text-emerald-700 hover:underline text-xs">
                                    Facture →
                                </a>
                            @endif
                        </td>
                        <td class="py-1 text-right">
                            <a href="{{ route('achats.attestation', $achat) }}" target="_blank" class="text-gray-600 hover:underline text-xs whitespace-nowrap">
                                📄 Attestation
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="py-4 text-center text-gray-400">Aucun achat ne correspond aux critères.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-2">{{ $achats->links() }}</div>
</div>
