<div class="p-4 sm:p-6 lg:p-8">
    @if (! $investisseur)
        <div class="bg-or-50 border border-or-300 rounded-champ p-4 text-sm text-or-700">
            {{ __("Votre compte n'est relié à aucun dossier investisseur. Contactez votre gestionnaire.") }}
        </div>
    @else
        <x-surtitre>{{ __('Mon espace') }}</x-surtitre>
        <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 mb-1">
            {{ __("Bonjour :prenom", ["prenom" => $investisseur->prenom ?: $investisseur->nom]) }}
        </h1>
        <p class="text-sm text-gray-500 mb-6 font-mono">{{ $investisseur->identifiant_externe }}</p>

        @php($actionsCommerciales = $comptesEnrichis->firstWhere('compte.categorie', 'commercial')['nombre_actions'] ?? 0)
        @php($actionsWaqf = $comptesEnrichis->firstWhere('compte.categorie', 'waqf')['nombre_actions'] ?? 0)
        @php($soldeTotal = $comptesEnrichis->sum('solde'))

        {{--
            Le même bandeau que sur la fiche, mais dit à la première personne :
            celui qui ouvre cet écran vient pour savoir où il en est, et il
            devait jusqu'ici le reconstituer compte par compte.
        --}}
        <div class="mb-6 overflow-hidden rounded-carte bg-nuit-900 shadow-sm">
            <div class="flex flex-col gap-6 p-6 lg:flex-row lg:items-center lg:gap-10">
                <div class="lg:w-56 lg:shrink-0">
                    <x-surtitre class="text-white/40">{{ __("Position") }}</x-surtitre>
                    <p class="mt-1 text-lg font-bold text-white">{{ __("Ce que je détiens") }}</p>
                    <p class="mt-1 text-sm text-white/60">
                        {{ __(":nombre compte(s)", ['nombre' => $comptesEnrichis->count()]) }}
                    </p>
                </div>

                <div class="grid flex-1 grid-cols-1 gap-5 sm:grid-cols-3 lg:gap-6 lg:divide-x lg:divide-white/10 rtl:lg:divide-x-reverse">
                    @foreach ([
                        ['etiquette' => __("Actions commerciales"), 'valeur' => \App\Support\Montant::format($actionsCommerciales), 'accent' => false],
                        ['etiquette' => __("Actions waqf"), 'valeur' => \App\Support\Montant::format($actionsWaqf), 'accent' => false],
                        ['etiquette' => __("Solde total"), 'valeur' => \App\Support\Montant::avecDevise($soldeTotal), 'accent' => true],
                    ] as $i => $chiffre)
                        <div class="{{ $i > 0 ? 'lg:ps-6' : '' }}">
                            <p class="text-xs font-medium uppercase tracking-wide text-white/50">{{ $chiffre['etiquette'] }}</p>
                            <p class="mt-1 text-xl font-bold sm:text-2xl {{ $chiffre['accent'] ? 'text-primaire-400' : 'text-white' }}">
                                {{ $chiffre['valeur'] }}
                            </p>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        @forelse ($comptesEnrichis as $item)
            <div class="bg-white border rounded-carte p-5 mb-4">
                <div class="flex flex-wrap items-center gap-3 mb-4">
                    <span class="px-2 py-1 text-xs font-semibold rounded {{ $item['compte']->categorie === 'commercial' ? 'bg-primaire-100 text-primaire-800' : 'bg-nuit-900 text-primaire-100' }}">
                        {{ __(\App\Support\Libelles::categorie($item['compte']->categorie)) }}
                    </span>
                    <span class="font-mono text-sm text-gray-500">{{ $item['compte']->numero_compte }}</span>
                    <span class="text-xs px-2 py-1 rounded-full {{ $item['compte']->reinvestissement_auto ? 'bg-primaire-100 text-primaire-700' : 'bg-gray-100 text-gray-600' }}">
                        {{ __("Réinvestissement auto") }} : {{ $item['compte']->reinvestissement_auto ? __('Oui') : __('Non') }}
                    </span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-6 mb-4">
                    <div>
                        <div class="text-xs text-gray-500 uppercase">{{ __("Actions détenues") }}</div>
                        <div class="text-2xl font-semibold text-gray-800">{{ \App\Support\Montant::format($item['nombre_actions']) }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-500 uppercase">{{ __("Solde du compte") }}</div>
                        <div class="text-2xl font-semibold text-primaire-700">{{ \App\Support\Montant::format($item['solde']) }}&#8239;CFA</div>
                    </div>
                </div>

                @if ($item['achats']->isNotEmpty())
                    <div class="mb-4">
                        <div class="text-xs text-gray-500 uppercase mb-2">{{ __("Mes achats") }}</div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm min-w-[420px]">
                                <thead class="text-start text-gray-500">
                                    <tr>
                                        <th class="py-1">{{ __("Date") }}</th>
                                        <th class="py-1 text-end">{{ __("Actions") }}</th>
                                        <th class="py-1 text-end">{{ __("Montant") }}</th>
                                        <th class="py-1"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y">
                                    @foreach ($item['achats'] as $achat)
                                        <tr>
                                            <td class="py-1">{{ $achat->date_achat->format('d/m/Y') }}</td>
                                            <td class="py-1 text-end">{{ \App\Support\Montant::format($achat->nombre_actions) }}</td>
                                            <td class="py-1 text-end">{{ \App\Support\Montant::format($achat->montant) }}</td>
                                            <td class="py-1 text-end">
                                                <a href="{{ route('achats.attestation', $achat) }}" target="_blank" class="text-gray-600 hover:underline text-xs whitespace-nowrap">
                                                    {{ __("📄 Attestation") }}
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
                        <div class="text-xs text-gray-500 uppercase mb-2">{{ __("Derniers mouvements") }}</div>
                        <table class="w-full text-sm min-w-[420px]">
                            <thead class="text-start text-gray-500">
                                <tr>
                                    <th class="py-1">{{ __("Date") }}</th>
                                    <th class="py-1">{{ __("Type") }}</th>
                                    <th class="py-1 text-end">{{ __("Montant") }}</th>
                                    <th class="py-1"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                @foreach ($item['dernieres_ecritures'] as $ecriture)
                                    <tr>
                                        <td class="py-1">{{ $ecriture->date_ecriture->format('d/m/Y') }}</td>
                                        <td class="py-1">{{ __(\App\Support\Libelles::typeEcriture($ecriture->type_ecriture)) }}</td>
                                        <td class="py-1 text-end {{ $ecriture->montant >= 0 ? 'text-primaire-700' : 'text-red-600' }}">
                                            {{ $ecriture->montant >= 0 ? '+' : '' }}{{ \App\Support\Montant::format($ecriture->montant) }}
                                        </td>
                                        <td class="py-1 text-end">
                                            @if ($ecriture->type_ecriture === 'paiement')
                                                <a href="{{ route('ecritures.attestation', $ecriture) }}" target="_blank" class="text-gray-600 hover:underline text-xs whitespace-nowrap">
                                                    {{ __("📄 Attestation") }}
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
            <div class="bg-white border rounded-carte p-8 text-center text-gray-400">
                {{ __("Aucun compte d'investissement pour l'instant.") }}
            </div>
        @endforelse

        {{--
            Le relevé descend en bas, comme sur la fiche : on le prend en partant,
            une fois qu'on a vu où on en est.
        --}}
        <div class="mt-8 border-t border-gray-200 pt-6">
            <x-surtitre>{{ __("Relevé de compte") }}</x-surtitre>
            <form action="{{ route('portail.releve') }}" method="GET" target="_blank" class="mt-2 flex flex-wrap items-end gap-2">
                <div>
                    <label class="mb-1 block text-xs text-gray-500">{{ __("Du (optionnel)") }}</label>
                    <input type="date" name="date_debut" class="rounded-champ border-gray-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-xs text-gray-500">{{ __("Au (optionnel)") }}</label>
                    <input type="date" name="date_fin" class="rounded-champ border-gray-300 px-3 py-2 text-sm">
                </div>
                <button type="submit"
                        class="whitespace-nowrap rounded-champ border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-50">
                    {{ __("📄 Télécharger mon relevé (PDF)") }}
                </button>
            </form>
            <p class="mt-1 text-xs text-gray-400">{{ __("Laissez vide pour l'historique complet.") }}</p>
        </div>
    @endif
</div>
