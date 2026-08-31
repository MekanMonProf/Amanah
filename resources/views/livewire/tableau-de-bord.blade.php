<div class="p-4 sm:p-6">
    <h1 class="text-xl sm:text-2xl font-semibold text-gray-800 mb-6">Tableau de bord</h1>

    @if ($vue === 'gestionnaire')
        @if (isset($aucunPortefeuille))
            <div class="bg-amber-50 border border-amber-200 rounded-lg p-4 text-sm text-amber-800">
                Votre compte utilisateur n'est relié à aucun profil gestionnaire. Contactez un administrateur.
            </div>
        @else
            {{-- Vue Gestionnaire : mon portefeuille --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                <div class="bg-white border rounded-lg p-4">
                    <div class="text-xs text-gray-500 uppercase">Mes investisseurs</div>
                    <div class="text-2xl font-semibold text-gray-800">{{ $nbInvestisseurs }}</div>
                </div>
                <div class="bg-white border rounded-lg p-4">
                    <div class="text-xs text-gray-500 uppercase">Comptes actifs</div>
                    <div class="text-2xl font-semibold text-gray-800">{{ $nbComptes }}</div>
                </div>
                <div class="bg-white border rounded-lg p-4">
                    <div class="text-xs text-gray-500 uppercase">Actions détenues</div>
                    <div class="text-2xl font-semibold text-gray-800">{{ number_format($totalActions, 0, ',', ' ') }}</div>
                </div>
                <div class="bg-white border rounded-lg p-4">
                    <div class="text-xs text-gray-500 uppercase">Solde cumulé</div>
                    <div class="text-2xl font-semibold text-emerald-700">{{ number_format($totalSolde, 0, ',', ' ') }} CFA</div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="bg-white border rounded-lg p-4">
                    <h2 class="font-semibold text-gray-800 mb-3">Investisseurs récents</h2>
                    @forelse ($mesInvestisseurs as $inv)
                        <a href="{{ route('investisseurs.show', $inv) }}" wire:navigate class="flex justify-between py-2 border-b last:border-0 hover:bg-gray-50 -mx-2 px-2 rounded">
                            <span class="text-sm">{{ $inv->nom }} {{ $inv->prenom }}</span>
                            <span class="text-xs text-gray-400 font-mono">{{ $inv->identifiant_externe }}</span>
                        </a>
                    @empty
                        <p class="text-sm text-gray-400">Aucun investisseur pour l'instant.</p>
                    @endforelse
                </div>

                <div class="bg-white border rounded-lg p-4">
                    <h2 class="font-semibold text-gray-800 mb-3">Derniers achats</h2>
                    @forelse ($dernierAchats as $achat)
                        <div class="flex justify-between py-2 border-b last:border-0 text-sm">
                            <span class="font-mono text-xs text-gray-500">{{ $achat->numero_achat }}</span>
                            <span>{{ $achat->nombre_actions }} action(s)</span>
                            <span class="text-gray-500">{{ number_format($achat->montant, 0, ',', ' ') }} CFA</span>
                        </div>
                    @empty
                        <p class="text-sm text-gray-400">Aucun achat pour l'instant.</p>
                    @endforelse
                </div>
            </div>
        @endif

    @else
        {{-- Vue globale : Direction / Administrateur / Lecture --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="bg-white border rounded-lg p-4">
                <div class="text-xs text-gray-500 uppercase">Investisseurs actifs</div>
                <div class="text-2xl font-semibold text-gray-800">{{ $nbInvestisseursActifs }}</div>
                @if ($nbInvestisseursSansGestionnaire > 0)
                    <div class="text-xs text-amber-600 mt-1">{{ $nbInvestisseursSansGestionnaire }} sans gestionnaire</div>
                @endif
            </div>
            <div class="bg-white border rounded-lg p-4">
                <div class="text-xs text-gray-500 uppercase">Gestionnaires actifs</div>
                <div class="text-2xl font-semibold text-gray-800">{{ $nbGestionnairesActifs }}</div>
            </div>
            <div class="bg-white border rounded-lg p-4">
                <div class="text-xs text-gray-500 uppercase">Dividendes distribués (total)</div>
                <div class="text-2xl font-semibold text-emerald-700">{{ number_format($totalDividendesDistribues, 0, ',', ' ') }} CFA</div>
            </div>
            <div class="bg-white border rounded-lg p-4">
                <div class="text-xs text-gray-500 uppercase">Dividendes ce mois-ci</div>
                <div class="text-2xl font-semibold text-gray-800">{{ number_format($dividendesCeMois, 0, ',', ' ') }} CFA</div>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
            <div class="bg-white border rounded-lg p-5">
                <h2 class="font-semibold text-gray-800 mb-3">Répartition par catégorie</h2>
                <div class="space-y-3">
                    @foreach (['commercial' => 'Commercial', 'waqf' => 'Waqf'] as $cle => $label)
                        <div>
                            <div class="flex justify-between text-sm mb-1">
                                <span class="font-medium">{{ $label }}</span>
                                <span class="text-gray-500">
                                    {{ $comptesParCategorie[$cle] ?? 0 }} compte(s) ·
                                    {{ number_format($actionsNettesParCategorie[$cle] ?? 0, 0, ',', ' ') }} action(s)
                                </span>
                            </div>
                            <div class="text-lg font-semibold text-gray-800">
                                {{ number_format($soldeParCategorie[$cle] ?? 0, 0, ',', ' ') }} CFA
                                <span class="text-xs text-gray-400 font-normal">de solde cumulé</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="bg-white border rounded-lg p-5">
                <h2 class="font-semibold text-gray-800 mb-3">Radiations</h2>
                <div class="text-xs text-gray-500 uppercase">Capital en attente de versement</div>
                <div class="text-2xl font-semibold {{ $radiationsEnAttente > 0 ? 'text-amber-600' : 'text-gray-800' }}">
                    {{ number_format($radiationsEnAttente, 0, ',', ' ') }} CFA
                </div>
                @if ($radiationsEnAttente > 0)
                    <p class="text-xs text-gray-400 mt-2">Consultez les fiches investisseurs concernées pour verser.</p>
                @endif
            </div>
        </div>

        <div class="bg-white border rounded-lg p-4">
            <h2 class="font-semibold text-gray-800 mb-3">Derniers investisseurs créés</h2>
            @forelse ($derniersInvestisseurs as $inv)
                <a href="{{ route('investisseurs.show', $inv) }}" wire:navigate class="flex justify-between py-2 border-b last:border-0 hover:bg-gray-50 -mx-2 px-2 rounded text-sm">
                    <span>{{ $inv->nom }} {{ $inv->prenom }}</span>
                    <span class="font-mono text-xs text-gray-400">{{ $inv->identifiant_externe }}</span>
                    <span class="text-gray-400 text-xs">{{ $inv->created_at->diffForHumans() }}</span>
                </a>
            @empty
                <p class="text-sm text-gray-400">Aucun investisseur pour l'instant.</p>
            @endforelse
        </div>
    @endif
</div>
