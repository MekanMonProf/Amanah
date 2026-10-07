<div class="space-y-6">
    <form wire:submit="enregistrer" class="bg-white border rounded-carte">
        <div class="p-5 border-b">
            <h2 class="font-semibold text-gray-800">{{ __("Vidéos du mode d'emploi") }}</h2>
            <p class="mt-1 text-sm text-gray-500">
                {{ __("Une adresse par sujet. Collez le lien d'intégration de la vidéo, pas celui de la page de partage — sur YouTube, c'est celui en /embed/. Un champ laissé vide n'affiche aucun emplacement sur la page.") }}
            </p>
            {{-- Les coordonnées ont rejoint « La société » : elles appartiennent à
                 l'identité de la maison, pas au mode d'emploi. --}}
            <p class="mt-2 text-sm text-gray-500">
                {{ __("Les coordonnées du support se règlent dans l'onglet « La société ».") }}
            </p>
        </div>

        <div class="p-5 space-y-3">
            @foreach ($sujets as $code => $sujet)
                <div class="grid grid-cols-1 gap-2 sm:grid-cols-[14rem_1fr] sm:items-center">
                    <label for="video-{{ $code }}" class="text-sm text-gray-700">{{ __($sujet['titre']) }}</label>
                    <div>
                        <input id="video-{{ $code }}" type="url" wire:model="videos.{{ $code }}"
                               placeholder="https://www.youtube.com/embed/…"
                               class="block w-full rounded-champ border-gray-300 px-3 py-2 text-sm focus:border-primaire-600 focus:ring-primaire-600">
                        <x-input-error :messages="$errors->get('videos.' . $code)" class="mt-1" />
                    </div>
                </div>
            @endforeach
        </div>

        <div class="flex items-center justify-end gap-3 border-t p-5">
            <x-primary-button>{{ __("Enregistrer") }}</x-primary-button>
        </div>
    </form>

    <div class="bg-white border rounded-carte">
        <div class="flex flex-wrap items-center justify-between gap-3 p-5 border-b">
            <div>
                <h2 class="font-semibold text-gray-800">{{ __("Demandes reçues") }}</h2>
                <p class="mt-1 text-sm text-gray-500">
                    {{ $nouvelles > 0
                        ? __(":nombre demande(s) jamais ouverte(s).", ['nombre' => $nouvelles])
                        : __("Rien en attente.") }}
                </p>
            </div>

            <select wire:model.live="filtreStatut"
                    class="rounded-champ border-gray-300 px-3 py-2 text-sm focus:border-primaire-600 focus:ring-primaire-600">
                <option value="ouvertes">{{ __("Ouvertes") }}</option>
                @foreach (\App\Models\DemandeSupport::STATUTS as $code => $libelle)
                    <option value="{{ $code }}">{{ __($libelle) }}</option>
                @endforeach
                <option value="toutes">{{ __("Toutes") }}</option>
            </select>
        </div>

        @forelse ($demandes as $demande)
            <div class="border-b last:border-0">
                <button type="button" wire:click="ouvrir({{ $demande->id }})"
                        class="flex w-full flex-wrap items-center gap-3 p-5 text-start transition hover:bg-gray-50">
                    <span class="shrink-0 rounded-full px-2 py-1 text-xs {{ $demande->statut === 'nouvelle' ? 'bg-or-50 text-or-700' : ($demande->statut === 'en_cours' ? 'bg-gray-100 text-gray-600' : 'bg-primaire-100 text-primaire-700') }}">
                        {{ __($demande->libelleStatut()) }}
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-sm font-medium text-gray-900">{{ $demande->sujet }}</span>
                        <span class="block text-xs text-gray-400">
                            {{ __("n° :numero", ['numero' => $demande->id]) }} ·
                            {{ $demande->auteur?->prenom }} {{ $demande->auteur?->nom ?? __("compte supprimé") }} ·
                            {{ __($demande->libelleCategorie()) }} ·
                            {{ $demande->created_at->format('d/m/Y H:i') }}
                        </span>
                    </span>
                </button>

                @if ($demandeOuverte === $demande->id)
                    <div class="border-t bg-gray-50 p-5">
                        <p class="whitespace-pre-line text-sm text-gray-800">{{ $demande->message }}</p>

                        @if ($demande->url_origine)
                            <p class="mt-3 text-xs text-gray-500">
                                {{ __("Écran d'origine") }} :
                                <a href="{{ $demande->url_origine }}" wire:navigate class="text-primaire-700 hover:underline">{{ $demande->url_origine }}</a>
                            </p>
                        @endif

                        <div class="mt-4">
                            <x-input-label for="reponse-{{ $demande->id }}" :value="__('Ce qui a été fait')" />
                            <textarea id="reponse-{{ $demande->id }}" wire:model="reponse" rows="3"
                                      class="mt-1 block w-full rounded-champ border-gray-300 px-3 py-2 text-sm focus:border-primaire-600 focus:ring-primaire-600"></textarea>
                        </div>

                        <div class="mt-3 flex flex-wrap gap-2">
                            @foreach (\App\Models\DemandeSupport::STATUTS as $code => $libelle)
                                @continue($code === $demande->statut)
                                <button type="button" wire:click="changerStatut({{ $demande->id }}, '{{ $code }}')"
                                        class="rounded-champ border border-gray-300 bg-white px-3 py-1.5 text-xs text-gray-700 transition hover:border-primaire-300 hover:bg-primaire-50 hover:text-primaire-700">
                                    {{ __("Marquer : :statut", ['statut' => __($libelle)]) }}
                                </button>
                            @endforeach
                        </div>

                        @if ($demande->traitePar)
                            <p class="mt-3 text-xs text-gray-400">
                                {{ __("Dernier geste par :nom, le :date.", [
                                    'nom' => trim($demande->traitePar->prenom . ' ' . $demande->traitePar->nom),
                                    'date' => $demande->traite_le?->format('d/m/Y H:i'),
                                ]) }}
                            </p>
                        @endif
                    </div>
                @endif
            </div>
        @empty
            <div class="p-8 text-center text-sm text-gray-400">
                {{ __("Aucune demande dans ce filtre.") }}
            </div>
        @endforelse
    </div>
</div>
