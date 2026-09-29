<div>
    @if (session('succes_financiers'))
        <div class="mb-4 text-sm text-emerald-800 bg-emerald-50 border border-emerald-200 rounded-lg px-4 py-3">
            ✓ {{ session('succes_financiers') }}
        </div>
    @endif

    @if (session('info_financiers'))
        <div class="mb-4 text-sm text-gray-700 bg-gray-50 border border-gray-200 rounded-lg px-4 py-3">
            {{ session('info_financiers') }}
        </div>
    @endif

    <form wire:submit="enregistrer" class="bg-white border rounded-lg shadow-sm">
        <div class="p-5 border-b">
            <h2 class="font-semibold text-gray-800">{{ __("Paramètres financiers") }}</h2>
            <p class="mt-1 text-sm text-gray-500">
                {{ __("Un changement de prix ne vaut que pour la suite : chaque achat déjà enregistré garde le prix auquel il a été conclu, et aucun historique n'est réécrit.") }}
            </p>
        </div>

        <div class="p-5 space-y-5">
            @foreach ($politiques as $politique)
                <div class="border rounded-lg p-4">
                    <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                        <h3 class="font-medium text-gray-800">
                            {{ __("Compte :categorie", ['categorie' => __(\App\Support\Libelles::categorie($politique->categorie))]) }}
                        </h3>
                        <span class="text-xs text-gray-500">
                            {{ __("Actuellement :prix CFA", ['prix' => \App\Support\Montant::format($politique->prix_unitaire_action)]) }}
                        </span>
                    </div>

                    <label class="text-sm text-gray-600" for="prix-{{ $politique->categorie }}">
                        {{ __("Prix unitaire de l'action (CFA)") }}
                    </label>
                    <input type="number" step="1" min="1" id="prix-{{ $politique->categorie }}"
                           wire:model="prix.{{ $politique->categorie }}"
                           class="w-full sm:w-64 border rounded px-3 py-2">
                    <x-input-error :messages="$errors->get('prix.' . $politique->categorie)" class="mt-1" />

                    {{-- Ces règles ne sont pas des réglages : elles tiennent à la nature
                         du compte. Le capital d'un waqf est immobilisé, donc il ne se
                         cède pas et ses bénéfices ne se versent pas. Les afficher sans
                         les rendre modifiables évite qu'on les cherche ailleurs. --}}
                    <dl class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-1 text-xs text-gray-500">
                        <div class="flex justify-between border-b py-1">
                            <dt>{{ __("Éligible aux dividendes") }}</dt>
                            <dd class="font-medium text-gray-700">{{ $politique->eligible_dividendes ? __('Oui') : __('Non') }}</dd>
                        </div>
                        <div class="flex justify-between border-b py-1">
                            <dt>{{ __("Versement des bénéfices") }}</dt>
                            <dd class="font-medium text-gray-700">{{ $politique->versement_dividendes_possible ? __('Oui') : __('Non') }}</dd>
                        </div>
                        <div class="flex justify-between border-b py-1">
                            <dt>{{ __("Cession autorisée") }}</dt>
                            <dd class="font-medium text-gray-700">{{ $politique->cession_autorisee ? __('Oui') : __('Non') }}</dd>
                        </div>
                        <div class="flex justify-between border-b py-1">
                            <dt>{{ __("Radiation autorisée") }}</dt>
                            <dd class="font-medium text-gray-700">{{ $politique->radiation_autorisee ? __('Oui') : __('Non') }}</dd>
                        </div>
                    </dl>
                    <p class="mt-2 text-xs text-gray-400">
                        {{ __("Ces quatre règles tiennent à la nature du compte et ne se règlent pas ici.") }}
                    </p>
                </div>
            @endforeach

            <div class="border rounded-lg p-4">
                <h3 class="font-medium text-gray-800 mb-3">{{ __("Éligibilité aux dividendes") }}</h3>

                <label class="text-sm text-gray-600" for="delai">{{ __("Délai de carence, en jours avant la fin du mois") }}</label>
                <input type="number" step="1" min="0" max="28" id="delai"
                       wire:model="delaiEligibilite" class="w-full sm:w-64 border rounded px-3 py-2">
                <x-input-error :messages="$errors->get('delaiEligibilite')" class="mt-1" />

                <p class="mt-2 text-xs text-gray-500">
                    {{ __("Un achat effectué dans ces derniers jours ne compte pas pour le dividende du mois : il n'a rien financé. À 0, toute souscription du mois donne droit à la distribution.") }}
                </p>
            </div>
        </div>

        <div class="p-5 border-t">
            <button type="submit" class="text-sm text-white bg-emerald-600 rounded-lg px-4 py-2 hover:bg-emerald-700">
                {{ __("Enregistrer les paramètres") }}
            </button>
        </div>
    </form>
</div>
