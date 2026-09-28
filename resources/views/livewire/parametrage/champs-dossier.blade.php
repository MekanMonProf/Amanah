<div>
    @if (session('succes_champs'))
        <div class="mb-4 text-sm text-emerald-800 bg-emerald-50 border border-emerald-200 rounded-lg px-4 py-3">
            ✓ {{ session('succes_champs') }}
        </div>
    @endif

    @if (session('info_champs'))
        <div class="mb-4 text-sm text-gray-700 bg-gray-50 border border-gray-200 rounded-lg px-4 py-3">
            {{ session('info_champs') }}
        </div>
    @endif

    <form wire:submit="enregistrer" class="bg-white border rounded-lg shadow-sm">
        <div class="p-5 border-b">
            <h2 class="font-semibold text-gray-800">{{ __("Champs du dossier") }}</h2>
            <p class="mt-1 text-sm text-gray-500">
                {{ __("Un champ coché compte dans le signalement « dossier incomplet ». Rien n'est bloqué pour autant : un dossier auquel il manque une pièce reste utilisable, et le premier achat affiche un avertissement.") }}
            </p>
            <p class="mt-2 text-sm text-gray-600">
                {{ __("Avec le réglage actuel, :nombre dossier(s) sur :total sont signalés incomplets.", ['nombre' => $this->nombreIncomplets(), 'total' => \App\Models\Investisseur::count()]) }}
            </p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 divide-y lg:divide-y-0 lg:divide-x">
            <div class="p-5">
                <h3 class="text-xs text-gray-500 uppercase mb-3">{{ __("Personne physique") }}</h3>
                <div class="space-y-2">
                    @foreach ($cataloguePhysique as $champ => $libelle)
                        <label class="flex items-start gap-2 text-sm text-gray-700 cursor-pointer">
                            <input type="checkbox" wire:model="coches.physique.{{ $champ }}"
                                   class="mt-0.5 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500">
                            <span>{{ __($libelle) }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="p-5">
                <h3 class="text-xs text-gray-500 uppercase mb-3">{{ __("Personne morale") }}</h3>
                <p class="text-xs text-gray-500 mb-3">
                    {{ __("Le registre du commerce et l'identifiant fiscal tiennent lieu de pièce d'identité : une société n'a ni date de naissance, ni nationalité.") }}
                </p>
                <div class="space-y-2">
                    @foreach ($catalogueMorale as $champ => $libelle)
                        <label class="flex items-start gap-2 text-sm text-gray-700 cursor-pointer">
                            <input type="checkbox" wire:model="coches.morale.{{ $champ }}"
                                   class="mt-0.5 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500">
                            <span>{{ __($libelle) }}</span>
                        </label>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="p-5 border-t">
            <button type="submit" class="text-sm text-white bg-emerald-600 rounded-lg px-4 py-2 hover:bg-emerald-700">
                {{ __("Enregistrer les champs") }}
            </button>
        </div>
    </form>
</div>
