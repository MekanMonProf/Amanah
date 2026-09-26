@props([
    // Intitulé affiché sur le bouton.
    'titre',
    // Clé de mémorisation, commune à tous les blocs de même nature : replier
    // « Historique des achats » sur une fiche le replie sur toutes les autres,
    // parce que c'est la section qu'on ne veut plus voir, pas ce compte-là.
    'cle',
    // Identifiant DOM, unique par bloc — un investisseur peut avoir plusieurs
    // comptes, et le même intitulé revient alors sur la page.
    'id',
    // Nombre de lignes, affiché à côté de l'intitulé : replié, le bloc doit
    // quand même dire ce qu'il contient, sinon on l'ouvre pour rien.
    'nombre' => null,
    'ouvertParDefaut' => true,
])

<div class="mb-4" x-data="{ ouvert: $persist({{ $ouvertParDefaut ? 'true' : 'false' }}).as('amanah-bloc-{{ $cle }}') }">
    <button type="button"
            x-on:click="ouvert = ! ouvert"
            :aria-expanded="ouvert ? 'true' : 'false'"
            aria-controls="{{ $id }}"
            class="flex items-center gap-2 w-full text-start text-xs text-gray-500 uppercase mb-2 py-1 rounded hover:text-gray-700 focus:outline-none focus:ring-2 focus:ring-emerald-500">
        <svg class="w-3.5 h-3.5 shrink-0 transition-transform rtl:-scale-x-100"
             :class="ouvert ? 'rotate-90' : ''"
             viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 0 1 .02-1.06L11.168 10 7.23 6.29a.75.75 0 1 1 1.04-1.08l4.5 4.25a.75.75 0 0 1 0 1.08l-4.5 4.25a.75.75 0 0 1-1.06-.02Z" clip-rule="evenodd" />
        </svg>

        <span>{{ $titre }}</span>

        @if ($nombre !== null)
            <span class="text-gray-400 normal-case">({{ \App\Support\Montant::format($nombre) }})</span>
        @endif

        <span class="ms-auto normal-case text-gray-400" x-text="ouvert ? '{{ __('Masquer') }}' : '{{ __('Afficher') }}'"></span>
    </button>

    <div id="{{ $id }}" x-show="ouvert" x-cloak>
        {{ $slot }}
    </div>
</div>
