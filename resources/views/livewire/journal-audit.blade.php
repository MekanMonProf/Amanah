<div class="p-4 sm:p-6 lg:p-8">
    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-3 mb-1">
        <div>
            <x-surtitre>{{ __('Administration') }}</x-surtitre>
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900">{{ __("Journal d'audit") }}</h1>
            <p class="text-sm text-gray-500">{{ __("Historique des actions sensibles effectuées sur la plateforme.") }}</p>
        </div>
        <div class="flex gap-2 whitespace-nowrap">
            <a href="{{ route('export.audit.csv', ['recherche' => $recherche, 'action' => $filtreAction, 'entite' => $filtreEntite, 'date_debut' => $dateDebut, 'date_fin' => $dateFin]) }}"
               target="_blank" class="text-sm text-gray-700 border border-gray-300 rounded-champ px-4 py-2 hover:bg-gray-50">
                {{ __("Exporter CSV") }}
            </a>
            <a href="{{ route('export.audit.pdf', ['recherche' => $recherche, 'action' => $filtreAction, 'entite' => $filtreEntite, 'date_debut' => $dateDebut, 'date_fin' => $dateFin]) }}"
               target="_blank" class="text-sm text-gray-700 border border-gray-300 rounded-champ px-4 py-2 hover:bg-gray-50">
                {{ __("Exporter PDF") }}
            </a>
        </div>
    </div>
    {{--
        Ce que le filtre ramène. La page de résultats ne disait ni combien il y
        en avait, ni sur quelle étendue — or on vient ici avec une question de
        volume, à laquelle vingt-cinq lignes sur une page ne répondent pas.
    --}}
    <div class="mb-6 mt-4 overflow-hidden rounded-carte bg-nuit-900 shadow-sm">
        <div class="flex flex-col gap-6 p-6 lg:flex-row lg:items-center lg:gap-10">
            <div class="lg:w-56 lg:shrink-0">
                <x-surtitre class="text-white/40">{{ __("Journal") }}</x-surtitre>
                <p class="mt-1 text-lg font-bold text-white">
                    {{ $recherche || $filtreAction || $filtreEntite || $dateDebut || $dateFin
                        ? __("Ce que le filtre ramène")
                        : __("Tout ce qui a été tracé") }}
                </p>
                <p class="mt-1 text-sm text-white/60">
                    @if ($this->position['premiere'])
                        {{ __("Depuis le :date", ['date' => \Illuminate\Support\Carbon::parse($this->position['premiere'])->format('d/m/Y')]) }}
                    @else
                        {{ __("Aucune action tracée.") }}
                    @endif
                </p>
            </div>

            <div class="grid flex-1 grid-cols-1 gap-5 sm:grid-cols-3 lg:gap-6 lg:divide-x lg:divide-white/10 rtl:lg:divide-x-reverse">
                @foreach ([
                    ['etiquette' => __("Actions tracées"), 'valeur' => \App\Support\Montant::format($this->position['actions']), 'accent' => false],
                    ['etiquette' => __("Personnes concernées"), 'valeur' => \App\Support\Montant::format($this->position['personnes']), 'accent' => false],
                    ['etiquette' => __("Dernière action"), 'valeur' => $this->position['derniere']
                        ? \Illuminate\Support\Carbon::parse($this->position['derniere'])->format('d/m/Y H:i')
                        : '—', 'accent' => true],
                ] as $i => $chiffre)
                    <div class="{{ $i > 0 ? 'lg:ps-6' : '' }}">
                        <p class="text-xs font-medium uppercase tracking-wide text-white/50">{{ $chiffre['etiquette'] }}</p>
                        <p class="mt-1 text-xl font-bold sm:text-2xl {{ $chiffre['accent'] ? 'text-primaire-400' : 'text-white' }}">
                            <bdi>{{ $chiffre['valeur'] }}</bdi>
                        </p>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="flex flex-col sm:flex-row flex-wrap gap-3 mb-4">
        <input type="text" wire:model.live.debounce.300ms="recherche"
               placeholder="{{ __('Rechercher (utilisateur, email, id)...') }}"
               class="border rounded px-3 py-2 text-sm w-full sm:w-64">

        <select wire:model.live="filtreAction" class="border rounded px-3 py-2 text-sm">
            <option value="">{{ __("Toutes les actions") }}</option>
            @foreach ($actionsDisponibles as $a)
                <option value="{{ $a }}">{{ __(\App\Support\Libelles::actionAudit($a)) }}</option>
            @endforeach
        </select>

        <select wire:model.live="filtreEntite" class="border rounded px-3 py-2 text-sm">
            <option value="">{{ __("Toutes les entités") }}</option>
            @foreach ($entitesDisponibles as $e)
                <option value="{{ $e }}">{{ __(\App\Support\Libelles::entiteAudit($e)) }}</option>
            @endforeach
        </select>

        <input type="date" wire:model.live="dateDebut" class="border rounded px-3 py-2 text-sm" title="Du">
        <input type="date" wire:model.live="dateFin" class="border rounded px-3 py-2 text-sm" title="Au">

        @if ($recherche || $filtreAction || $filtreEntite || $dateDebut || $dateFin)
            <button wire:click="reinitialiserFiltres" class="text-sm text-gray-500 hover:underline self-center">
                {{ __("Réinitialiser") }}
            </button>
        @endif
    </div>

    <div class="bg-white border rounded-carte overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-start text-gray-600">
                <tr>
                    <th class="p-3">{{ __("Date") }}</th>
                    <th class="p-3">{{ __("Utilisateur") }}</th>
                    <th class="p-3">{{ __("Action") }}</th>
                    <th class="p-3">{{ __("Entité") }}</th>
                    <th class="p-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse ($entrees as $entree)
                    <tr>
                        <td class="p-3 text-xs text-gray-500 whitespace-nowrap">{{ $entree->created_at->format('d/m/Y H:i') }}</td>
                        <td class="p-3">{{ $entree->user?->nom }} {{ $entree->user?->prenom }}</td>
                        <td class="p-3">
                            <span class="px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-700">
                                {{ __(\App\Support\Libelles::actionAudit($entree->action)) }}
                            </span>
                        </td>
                        <td class="p-3 text-xs text-gray-500">
                            {{ __(\App\Support\Libelles::entiteAudit($entree->entite)) }}
                            @if ($entree->entite_id) #{{ $entree->entite_id }} @endif
                        </td>
                        <td class="p-3 text-end">
                            @if ($entree->donnees_avant || $entree->donnees_apres)
                                <button wire:click="basculerDetail({{ $entree->id }})" class="text-xs text-primaire-700 hover:underline">
                                    {{ in_array($entree->id, $lignesOuvertes) ? __('Masquer') : __('Détails') }}
                                </button>
                            @endif
                        </td>
                    </tr>
                    @if (in_array($entree->id, $lignesOuvertes) && ($entree->donnees_avant || $entree->donnees_apres))
                        <tr>
                            <td colspan="5" class="p-3 bg-gray-50 text-xs">
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    @if ($entree->donnees_avant)
                                        <div>
                                            <div class="text-gray-400 uppercase mb-1">{{ __("Avant") }}</div>
                                            <pre class="whitespace-pre-wrap font-mono text-gray-600">{{ json_encode($entree->donnees_avant, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                        </div>
                                    @endif
                                    @if ($entree->donnees_apres)
                                        <div>
                                            <div class="text-gray-400 uppercase mb-1">{{ __("Après") }}</div>
                                            <pre class="whitespace-pre-wrap font-mono text-gray-600">{{ json_encode($entree->donnees_apres, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                        </div>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr><td colspan="5" class="p-6 text-center text-gray-400">{{ __("Aucune entrée pour l'instant.") }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $entrees->links() }}</div>
</div>
