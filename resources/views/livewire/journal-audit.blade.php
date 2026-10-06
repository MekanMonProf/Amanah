<div class="p-4 sm:p-6">
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
    <div class="mb-6"></div>

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
