<div class="p-4 sm:p-6">
    @if (! $investisseur)
        <div class="bg-amber-50 border border-amber-200 rounded-lg p-4 text-sm text-amber-800">
            Votre compte n'est relié à aucun dossier investisseur. Contactez votre gestionnaire.
        </div>
    @else
        <h1 class="text-xl sm:text-2xl font-semibold text-gray-800 mb-1">
            Bonjour {{ $investisseur->prenom ?: $investisseur->nom }}
        </h1>
        <p class="text-sm text-gray-500 mb-2 font-mono">{{ $investisseur->identifiant_externe }}</p>
        <a href="{{ route('portail.releve') }}" target="_blank"
           class="inline-block mb-6 text-sm text-emerald-700 border border-emerald-700 rounded-lg px-4 py-2 hover:bg-emerald-50">
            📄 Télécharger mon relevé (PDF)
        </a>

        @forelse ($comptesEnrichis as $item)
            <div class="bg-white border rounded-lg p-5 mb-4 shadow-sm">
                <div class="flex justify-between items-center mb-4">
                    <span class="px-2 py-1 text-xs font-semibold rounded {{ $item['compte']->categorie === 'commercial' ? 'bg-blue-100 text-blue-700' : 'bg-purple-100 text-purple-700' }}">
                        {{ ucfirst($item['compte']->categorie) }}
                    </span>
                    <span class="text-xs px-2 py-1 rounded-full {{ $item['compte']->reinvestissement_auto ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-600' }}">
                        Réinvestissement auto : {{ $item['compte']->reinvestissement_auto ? 'Oui' : 'Non' }}
                    </span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-6 mb-4">
                    <div>
                        <div class="text-xs text-gray-500 uppercase">Actions détenues</div>
                        <div class="text-2xl font-semibold text-gray-800">{{ number_format($item['nombre_actions'], 0, ',', ' ') }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-500 uppercase">Solde du compte</div>
                        <div class="text-2xl font-semibold text-emerald-700">{{ number_format($item['solde'], 0, ',', ' ') }} CFA</div>
                    </div>
                </div>

                @if ($item['achats']->isNotEmpty())
                    <div class="mb-4">
                        <div class="text-xs text-gray-500 uppercase mb-2">Mes achats</div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm min-w-[420px]">
                                <thead class="text-left text-gray-500">
                                    <tr>
                                        <th class="py-1">Date</th>
                                        <th class="py-1 text-right">Actions</th>
                                        <th class="py-1 text-right">Montant</th>
                                        <th class="py-1"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y">
                                    @foreach ($item['achats'] as $achat)
                                        <tr>
                                            <td class="py-1">{{ $achat->date_achat->format('d/m/Y') }}</td>
                                            <td class="py-1 text-right">{{ number_format($achat->nombre_actions, 0, ',', ' ') }}</td>
                                            <td class="py-1 text-right">{{ number_format($achat->montant, 0, ',', ' ') }}</td>
                                            <td class="py-1 text-right">
                                                <a href="{{ route('achats.attestation', $achat) }}" target="_blank" class="text-gray-600 hover:underline text-xs whitespace-nowrap">
                                                    📄 Attestation
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

                @if ($item['dernieres_ecritures']->isNotEmpty())
                    <div class="overflow-x-auto">
                        <div class="text-xs text-gray-500 uppercase mb-2">Derniers mouvements</div>
                        <table class="w-full text-sm min-w-[420px]">
                            <thead class="text-left text-gray-500">
                                <tr>
                                    <th class="py-1">Date</th>
                                    <th class="py-1">Type</th>
                                    <th class="py-1 text-right">Montant</th>
                                    <th class="py-1"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                @foreach ($item['dernieres_ecritures'] as $ecriture)
                                    <tr>
                                        <td class="py-1">{{ $ecriture->date_ecriture->format('d/m/Y') }}</td>
                                        <td class="py-1">{{ str_replace('_', ' ', $ecriture->type_ecriture) }}</td>
                                        <td class="py-1 text-right {{ $ecriture->montant >= 0 ? 'text-emerald-700' : 'text-red-600' }}">
                                            {{ $ecriture->montant >= 0 ? '+' : '' }}{{ number_format($ecriture->montant, 0, ',', ' ') }}
                                        </td>
                                        <td class="py-1 text-right">
                                            @if ($ecriture->type_ecriture === 'paiement')
                                                <a href="{{ route('ecritures.attestation', $ecriture) }}" target="_blank" class="text-gray-600 hover:underline text-xs whitespace-nowrap">
                                                    📄 Attestation
                                                </a>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        @empty
            <div class="bg-white border rounded-lg p-8 text-center text-gray-400">
                Aucun compte d'investissement pour l'instant.
            </div>
        @endforelse
    @endif
</div>
