<div class="p-4 sm:p-6 lg:p-8">
    @if (! $investisseur)
        <div class="rounded-champ border border-or-300 bg-or-50 p-4 text-sm text-or-700">
            {{ __("Votre compte n'est relié à aucun dossier investisseur. Contactez votre gestionnaire.") }}
        </div>
    @else
        <x-surtitre>{{ __('Mon espace') }}</x-surtitre>
        <h1 class="mb-1 text-2xl font-bold text-gray-900 sm:text-3xl">{{ __("Mes documents") }}</h1>
        <p class="mb-6 font-mono text-sm text-gray-500">{{ $investisseur->identifiant_externe }}</p>

        @if ($total === 0)
            <div class="rounded-carte border bg-white p-8 text-center text-gray-400">
                {{ __("Aucun document pour l'instant : votre dossier n'enregistre encore aucune opération.") }}
            </div>
        @else
            <div class="overflow-hidden rounded-carte border border-gray-200 bg-white shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-5 py-4">
                    <div class="flex items-center gap-3">
                        <x-icone nom="documents" class="h-6 w-6 shrink-0 text-primaire-700" />
                        <h2 class="text-lg font-bold text-gray-900">{{ __("Vos attestations et reçus") }}</h2>
                    </div>
                    <span class="rounded-full bg-primaire-50 px-3 py-1 text-sm font-semibold text-primaire-700">
                        {{ __(":nombre document(s)", ['nombre' => $total]) }}
                    </span>
                </div>

                <x-liste-documents :documents="$documents" />
            </div>

            {{--
                Comme le relevé : rien n'est archivé, chaque pièce se fabrique à
                l'ouverture. Le dire évite qu'on cherche où « télécharger ses
                documents une bonne fois » — ils sont toujours là.
            --}}
            <p class="mt-3 text-xs text-gray-400">
                {{ __("Chaque document est établi au moment où vous l'ouvrez, à partir de l'opération qu'il atteste.") }}
            </p>
        @endif

        <div class="mt-8 border-t border-gray-200 pt-6">
            <x-surtitre>{{ __("Relevé de compte") }}</x-surtitre>
            <a href="{{ route('portail.releves.index') }}"
               class="mt-2 inline-flex items-center gap-2 rounded-champ border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-50">
                <x-icone nom="releves" class="h-5 w-5 shrink-0 text-primaire-700" />
                {{ __("Voir mes relevés mensuels") }}
            </a>
            <p class="mt-1 text-xs text-gray-400">
                {{ __("Le relevé récapitule un mois entier ; les documents ci-dessus attestent chacun d'une opération.") }}
            </p>
        </div>
    @endif
</div>
