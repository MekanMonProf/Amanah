<div class="p-4 sm:p-6 lg:p-8">
    @if (! $investisseur)
        <div class="rounded-champ border border-or-300 bg-or-50 p-4 text-sm text-or-700">
            {{ __("Votre compte n'est relié à aucun dossier investisseur. Contactez votre gestionnaire.") }}
        </div>
    @else
        <x-surtitre>{{ __('Mon espace') }}</x-surtitre>
        <h1 class="mb-1 text-2xl font-bold text-gray-900 sm:text-3xl">{{ __("Mes relevés") }}</h1>
        <p class="mb-6 font-mono text-sm text-gray-500">{{ $investisseur->identifiant_externe }}</p>

        @if ($total === 0)
            <div class="rounded-carte border bg-white p-8 text-center text-gray-400">
                {{ __("Aucun relevé pour l'instant : votre dossier n'enregistre encore aucun mouvement.") }}
            </div>
        @else
            <div class="overflow-hidden rounded-carte border border-gray-200 bg-white shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-5 py-4">
                    <div class="flex items-center gap-3">
                        <x-icone nom="releves" class="h-6 w-6 shrink-0 text-primaire-700" />
                        <h2 class="text-lg font-bold text-gray-900">{{ __("Vos relevés financiers") }}</h2>
                    </div>
                    <span class="rounded-full bg-primaire-50 px-3 py-1 text-sm font-semibold text-primaire-700">
                        {{ __(":nombre relevé(s)", ['nombre' => $total]) }}
                    </span>
                </div>

                <x-liste-releves :periodes="$periodes" route="portail.releve" />
            </div>

            {{--
                L'ancienne application déposait un fichier : il portait une date de
                dépôt, et ne changeait plus. Ici le relevé est établi à l'ouverture.
                Le dire évite qu'un actionnaire s'étonne de voir un document du même
                mois ne pas être identique d'une fois sur l'autre.
            --}}
            <p class="mt-3 text-xs text-gray-400">
                {{ __("Chaque relevé est établi au moment où vous l'ouvrez, à partir des mouvements enregistrés pour ce mois.") }}
            </p>
        @endif

        <div class="mt-8 border-t border-gray-200 pt-6">
            <x-surtitre>{{ __("Relevé d'une autre période") }}</x-surtitre>
            <p class="mt-1 text-sm text-gray-500">
                {{ __("Pour un relevé qui ne tient pas dans un mois — une année entière, ou l'historique complet.") }}
            </p>
            <form action="{{ route('portail.releve') }}" method="GET" target="_blank" class="mt-3 flex flex-wrap items-end gap-2">
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
