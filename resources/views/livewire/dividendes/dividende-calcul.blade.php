<div class="p-4 sm:p-6 max-w-2xl">
    <h1 class="text-xl sm:text-2xl font-semibold text-gray-800 mb-1">Calcul des dividendes</h1>
    <p class="text-sm text-gray-500 mb-6">
        Fixe le taux de la période choisie (une seule fois, définitif), puis rejoue automatiquement
        tout l'historique connu pour que chaque compte reçoive tout ce qu'il n'a pas encore perçu.
    </p>

    @if (session('succes_parametre'))
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-lg p-3 mb-4 text-sm">
            {{ session('succes_parametre') }}
        </div>
    @endif

    <div class="bg-gray-50 border rounded-lg p-4 mb-6">
        <div class="flex justify-between items-center">
            <div>
                <div class="text-sm font-medium text-gray-700">Règle d'éligibilité en fin de mois</div>
                @if (!$modifierDelai)
                    <p class="text-sm text-gray-500 mt-1">
                        @if ($delaiEligibiliteJours === 0)
                            Un achat compte pour le dividende du mois en cours, quel que soit le jour du mois.
                        @else
                            Un achat effectué dans les <strong>{{ $delaiEligibiliteJours }} derniers jours</strong> du mois
                            ne compte qu'à partir du mois suivant.
                        @endif
                    </p>
                @endif
            </div>
            <button type="button" wire:click="$toggle('modifierDelai')" class="text-sm text-emerald-700 hover:underline whitespace-nowrap">
                {{ $modifierDelai ? 'Annuler' : 'Modifier' }}
            </button>
        </div>

        @if ($modifierDelai)
            <div class="flex items-end gap-3 mt-3">
                <div>
                    <label class="text-xs text-gray-500">Nombre de jours avant la fin du mois</label>
                    <input type="number" min="0" max="30" wire:model="delaiEligibiliteJours" class="border rounded px-3 py-1.5 w-24">
                </div>
                <button type="button" wire:click="enregistrerDelai" class="bg-emerald-700 text-white px-4 py-1.5 rounded-lg text-sm">
                    Enregistrer
                </button>
            </div>
            @error('delaiEligibiliteJours') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
        @endif
    </div>

    <form wire:submit="calculerEtDistribuer" class="bg-white border rounded-lg p-5 shadow-sm space-y-4">
        <div>
            <label class="text-sm text-gray-600">Période (mois concerné)</label>
            <input type="month" wire:model.live="periode"
                   value="{{ \Illuminate\Support\Carbon::parse($periode)->format('Y-m') }}"
                   class="w-full border rounded px-3 py-2">
            @error('periode') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="text-sm text-gray-600">Bénéfice par action — Commercial (CFA)</label>
                <input type="number" step="0.01" wire:model="benefice_commercial"
                       {{ $baremeCommercialExistant ? 'disabled' : '' }}
                       class="w-full border rounded px-3 py-2 {{ $baremeCommercialExistant ? 'bg-gray-50 text-gray-500' : '' }}">
                @error('benefice_commercial') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                @if ($baremeCommercialExistant)
                    <p class="text-xs text-amber-600 mt-1">🔒 Déjà fixé pour cette période — non modifiable.</p>
                @endif
            </div>
            <div>
                <label class="text-sm text-gray-600">Bénéfice par action — Waqf (CFA)</label>
                <input type="number" step="0.01" wire:model="benefice_waqf"
                       {{ $baremeWaqfExistant ? 'disabled' : '' }}
                       class="w-full border rounded px-3 py-2 {{ $baremeWaqfExistant ? 'bg-gray-50 text-gray-500' : '' }}">
                @error('benefice_waqf') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                @if ($baremeWaqfExistant)
                    <p class="text-xs text-amber-600 mt-1">🔒 Déjà fixé pour cette période — non modifiable.</p>
                @endif
            </div>
        </div>

        <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 text-sm text-blue-800">
            ℹ️ Chaque lancement rejoue automatiquement <strong>tout l'historique</strong> des barèmes déjà fixés,
            pas seulement cette période. Un compte ouvert après coup rattrape ainsi tous les mois qu'il a manqués,
            avec le taux qui était en vigueur à chaque période.
        </div>

        <button type="submit" wire:loading.attr="disabled" wire:target="calculerEtDistribuer"
                class="bg-emerald-700 text-white px-5 py-2 rounded-lg w-full sm:w-auto disabled:opacity-50 disabled:cursor-not-allowed">
            <span wire:loading.remove wire:target="calculerEtDistribuer">Calculer et distribuer</span>
            <span wire:loading wire:target="calculerEtDistribuer">Calcul en cours...</span>
        </button>
    </form>

    @if ($resultats)
        <div class="mt-6 space-y-4">
            <h2 class="font-semibold text-gray-800">Résultat de ce passage (toutes périodes confondues)</h2>

            @foreach (['commercial' => 'Commercial', 'waqf' => 'Waqf'] as $cle => $label)
                <div class="bg-white border rounded-lg p-4">
                    <div class="flex justify-between items-center mb-2">
                        <span class="px-2 py-1 text-xs font-semibold rounded {{ $cle === 'commercial' ? 'bg-blue-100 text-blue-700' : 'bg-purple-100 text-purple-700' }}">
                            {{ $label }}
                        </span>
                    </div>
                    <div class="grid grid-cols-3 gap-3 text-sm">
                        <div>
                            <div class="text-xs text-gray-500">Comptes crédités</div>
                            <div class="text-lg font-semibold text-gray-800">{{ $resultats[$cle]['nb_comptes'] }}</div>
                        </div>
                        <div>
                            <div class="text-xs text-gray-500">Déjà à jour (ignorés)</div>
                            <div class="text-lg font-semibold text-gray-400">{{ $resultats[$cle]['deja_traites'] }}</div>
                        </div>
                        <div>
                            <div class="text-xs text-gray-500">Total distribué</div>
                            <div class="text-lg font-semibold text-emerald-700">{{ \App\Support\Montant::format($resultats[$cle]['total_distribue']) }}&#8239;CFA</div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    @if (!empty($baremesHistorique))
        <div class="mt-8">
            <h2 class="font-semibold text-gray-800 mb-3">Historique des barèmes fixés</h2>
            <table class="w-full bg-white border rounded-lg text-sm">
                <thead class="bg-gray-50 text-left text-gray-600">
                    <tr>
                        <th class="p-2">Période</th>
                        <th class="p-2 text-right">Commercial</th>
                        <th class="p-2 text-right">Waqf</th>
                        <th class="p-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @foreach ($baremesHistorique as $mois => $parCategorie)
                        <tr>
                            <td class="p-2">{{ \Illuminate\Support\Carbon::createFromFormat('Y-m', $mois)->translatedFormat('F Y') }}</td>
                            <td class="p-2 text-right">
                                {{ isset($parCategorie['commercial']) ? \App\Support\Montant::format($parCategorie['commercial']['benefice_par_action']) . " CFA" : '—' }}
                            </td>
                            <td class="p-2 text-right">
                                {{ isset($parCategorie['waqf']) ? \App\Support\Montant::format($parCategorie['waqf']['benefice_par_action']) . " CFA" : '—' }}
                            </td>
                            <td class="p-2 text-right whitespace-nowrap">
                                @if (isset($parCategorie['commercial']))
                                    <a href="{{ route('baremes.corriger', $parCategorie['commercial']['id']) }}" wire:navigate class="text-xs text-red-600 hover:underline mr-2">Corriger Com.</a>
                                @endif
                                @if (isset($parCategorie['waqf']))
                                    <a href="{{ route('baremes.corriger', $parCategorie['waqf']['id']) }}" wire:navigate class="text-xs text-red-600 hover:underline">Corriger Waqf</a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
