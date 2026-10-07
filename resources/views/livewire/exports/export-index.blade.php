@php
    // Un export global = un intitulé, une explication, et deux routes.
    $exports = [
        [
            'titre' => "Investisseurs",
            'note' => "Coordonnées, gestionnaire et statut de tous les investisseurs.",
            'csv' => 'export.investisseurs.csv',
            'pdf' => 'export.investisseurs.pdf',
            'date' => false,
        ],
        [
            'titre' => "Achats d'actions",
            'note' => "Tous les achats, avec le compte, le nombre d'actions et le montant.",
            'csv' => 'export.achats.global.csv',
            'pdf' => 'export.achats.global.pdf',
            'date' => true,
        ],
        [
            'titre' => "Écritures du compte financier",
            'note' => "Versements, paiements et ajustements portés aux comptes.",
            'csv' => 'export.ecritures.global.csv',
            'pdf' => 'export.ecritures.global.pdf',
            'date' => true,
        ],
        [
            'titre' => "Radiations",
            'note' => "Actions radiées, avec leur motif et leur contrepartie.",
            'csv' => 'export.radiations.global.csv',
            'pdf' => 'export.radiations.global.pdf',
            'date' => true,
        ],
    ];

    // Le journal d'audit suit le même droit d'accès que l'écran qui l'affiche.
    if (in_array(auth()->user()->role, ['direction', 'administrateur'], true)) {
        $exports[] = [
            'titre' => "Journal d'audit",
            'note' => "Toutes les actions enregistrées, avec leur auteur et leur horodatage.",
            'csv' => 'export.audit.csv',
            'pdf' => 'export.audit.pdf',
            'date' => true,
        ];
    }
@endphp

<div class="p-4 sm:p-6 lg:p-8">
    <div class="mb-6">
        <x-surtitre>{{ __('Administration') }}</x-surtitre>
        <h1 class="text-2xl sm:text-3xl font-bold text-gray-900">{{ __("Exports") }}</h1>
        <p class="mt-1 text-sm text-gray-500">
            {{ __("Les fichiers reprennent ce que vous êtes autorisé à consulter : un gestionnaire n'exporte que son portefeuille.") }}
        </p>
    </div>

    {{-- Période commune aux exports datés --}}
    <div class="bg-white border rounded-carte p-4 mb-6">
        <p class="text-sm font-medium text-gray-700 mb-3">{{ __("Période") }}</p>
        <div class="flex flex-col sm:flex-row gap-3 sm:items-end">
            <div>
                <label for="export-date-debut" class="block text-sm text-gray-600 mb-1">{{ __("Du") }}</label>
                <input id="export-date-debut" type="date" wire:model.live="dateDebut" class="border-gray-300 rounded-champ text-sm">
            </div>
            <div>
                <label for="export-date-fin" class="block text-sm text-gray-600 mb-1">{{ __("Au") }}</label>
                <input id="export-date-fin" type="date" wire:model.live="dateFin" class="border-gray-300 rounded-champ text-sm">
            </div>
            @if ($periode !== [])
                <button type="button" wire:click="reinitialiser"
                        class="text-sm text-gray-600 border border-gray-300 rounded-champ px-4 py-2 hover:bg-gray-50">
                    {{ __("Toute la période") }}
                </button>
            @endif
        </div>
        <p class="mt-3 text-xs text-gray-500">
            {{ __("Sans période, l'export porte sur l'intégralité de l'historique.") }}
        </p>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @foreach ($exports as $export)
            <div class="bg-white border rounded-carte p-4 flex flex-col">
                <h2 class="font-medium text-gray-800">{{ __($export['titre']) }}</h2>
                <p class="mt-1 text-sm text-gray-500 flex-1">{{ __($export['note']) }}</p>

                @unless ($export['date'])
                    <p class="mt-2 text-xs text-gray-400">{{ __("Cet export ne dépend pas de la période.") }}</p>
                @endunless

                <div class="mt-4 flex gap-2">
                    <a href="{{ route($export['csv'], $export['date'] ? $periode : []) }}" target="_blank"
                       class="flex-1 text-sm text-center text-gray-700 border border-gray-300 rounded-champ px-4 py-2 hover:bg-gray-50">
                        {{ __("Exporter CSV") }}
                    </a>
                    <a href="{{ route($export['pdf'], $export['date'] ? $periode : []) }}" target="_blank"
                       class="flex-1 text-sm text-center text-gray-700 border border-gray-300 rounded-champ px-4 py-2 hover:bg-gray-50">
                        {{ __("Exporter PDF") }}
                    </a>
                </div>
            </div>
        @endforeach
    </div>
</div>
