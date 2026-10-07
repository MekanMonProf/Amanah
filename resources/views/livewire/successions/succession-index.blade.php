<div class="p-4 sm:p-6">
    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3 mb-6">
        <x-surtitre>{{ __('Finance') }}</x-surtitre>
        <h1 class="text-2xl sm:text-3xl font-bold text-gray-900">{{ __("Successions") }}</h1>
    </div>

    {{-- Filtres --}}
    <div class="bg-white border rounded-carte p-4 mb-4 flex flex-col sm:flex-row gap-3 sm:items-end">
        <div class="flex-1">
            <label for="recherche-succession" class="block text-sm text-gray-600 mb-1">{{ __("Rechercher") }}</label>
            <input id="recherche-succession" type="search" wire:model.live.debounce.300ms="recherche"
                   placeholder="{{ __("Nom, prénom ou identifiant") }}"
                   class="w-full border-gray-300 rounded-champ text-sm">
        </div>

        <div class="sm:w-64">
            <label for="etat-succession" class="block text-sm text-gray-600 mb-1">{{ __("État du dossier") }}</label>
            <select id="etat-succession" wire:model.live="filtreEtat" class="w-full border-gray-300 rounded-champ text-sm">
                <option value="en_cours">{{ __("À régler") }} ({{ $nombreEnCours }})</option>
                <option value="reglee">{{ __("Réglées") }} ({{ $nombreReglees }})</option>
                <option value="">{{ __("Toutes") }} ({{ $nombreEnCours + $nombreReglees }})</option>
            </select>
        </div>
    </div>

    @if ($successions->isEmpty())
        <div class="bg-white border rounded-carte p-8 text-center text-gray-500 text-sm">
            {{ __("Aucune succession à afficher.") }}
            <p class="mt-2 text-gray-400">
                {{ __("Un dossier de succession s'ouvre en déclarant le décès depuis la fiche de l'investisseur.") }}
            </p>
        </div>
    @else
        {{-- Tableau, à partir de sm --}}
        <table class="hidden sm:table w-full bg-white border rounded-carte overflow-hidden">
            <thead class="bg-gray-50 text-start text-sm text-gray-600">
                <tr>
                    <th class="p-3">{{ __("Identifiant") }}</th>
                    <th class="p-3">{{ __("Nom") }}</th>
                    <th class="p-3">{{ __("Date du décès") }}</th>
                    <th class="p-3">{{ __("Héritiers") }}</th>
                    <th class="p-3">{{ __("Gestionnaire") }}</th>
                    <th class="p-3">{{ __("État du dossier") }}</th>
                    <th class="p-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @foreach ($successions as $succession)
                    <tr class="text-sm hover:bg-gray-50">
                        <td class="p-3 text-gray-500">{{ $succession->identifiant_externe }}</td>
                        <td class="p-3">
                            <a href="{{ route('investisseurs.show', $succession) }}" wire:navigate
                               class="text-primaire-700 hover:underline">
                                <bdi>{{ $succession->nom }} {{ $succession->prenom }}</bdi>
                            </a>
                        </td>
                        <td class="p-3">{{ $succession->date_deces?->format('d/m/Y') ?? '—' }}</td>
                        <td class="p-3">
                            @if ($succession->heritiers_count === 0)
                                <span class="text-or-700">{{ __("Aucun héritier saisi") }}</span>
                            @else
                                {{ $succession->heritiers_count }}
                            @endif
                        </td>
                        <td class="p-3">
                            <bdi>{{ $succession->gestionnaire?->user ? $succession->gestionnaire->user->nom . ' ' . $succession->gestionnaire->user->prenom : '—' }}</bdi>
                        </td>
                        <td class="p-3">
                            @if ($succession->succession_reglee)
                                <span class="inline-block px-2 py-1 rounded-full text-xs bg-primaire-50 text-primaire-800">{{ __("Réglée") }}</span>
                            @else
                                <span class="inline-block px-2 py-1 rounded-full text-xs bg-or-50 text-or-700">{{ __("À régler") }}</span>
                            @endif
                        </td>
                        <td class="p-3 text-end">
                            <a href="{{ route('successions.gerer', $succession) }}" wire:navigate
                               class="text-sm text-primaire-700 hover:underline whitespace-nowrap">
                                {{ __("Gérer") }} →
                            </a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        {{-- Cartes, en dessous de sm --}}
        <div class="sm:hidden space-y-3">
            @foreach ($successions as $succession)
                <div class="bg-white border rounded-carte p-4">
                    <div class="flex justify-between items-start gap-3">
                        <div class="min-w-0">
                            <a href="{{ route('investisseurs.show', $succession) }}" wire:navigate
                               class="font-medium text-primaire-700 hover:underline">
                                <bdi>{{ $succession->nom }} {{ $succession->prenom }}</bdi>
                            </a>
                            <p class="text-xs text-gray-500">{{ $succession->identifiant_externe }}</p>
                        </div>
                        @if ($succession->succession_reglee)
                            <span class="shrink-0 px-2 py-1 rounded-full text-xs bg-primaire-50 text-primaire-800">{{ __("Réglée") }}</span>
                        @else
                            <span class="shrink-0 px-2 py-1 rounded-full text-xs bg-or-50 text-or-700">{{ __("À régler") }}</span>
                        @endif
                    </div>

                    <dl class="mt-3 text-sm space-y-1">
                        <div class="flex justify-between gap-3">
                            <dt class="text-gray-500">{{ __("Date du décès") }}</dt>
                            <dd>{{ $succession->date_deces?->format('d/m/Y') ?? '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-gray-500">{{ __("Héritiers") }}</dt>
                            <dd>{{ $succession->heritiers_count }}</dd>
                        </div>
                    </dl>

                    <a href="{{ route('successions.gerer', $succession) }}" wire:navigate
                       class="mt-3 inline-block text-sm text-primaire-700 hover:underline">
                        {{ __("Gérer") }} →
                    </a>
                </div>
            @endforeach
        </div>

        <div class="mt-4">{{ $successions->links() }}</div>
    @endif
</div>
