@props([
    // Intitulé affiché sur le bouton.
    'titre',
    // Clé de la section, commune à tous les blocs de même nature : replier
    // « Historique des achats » sur une fiche le replie sur toutes les autres,
    // parce que c'est la section qu'on ne veut plus voir, pas ce compte-là.
    'cle',
    // Nombre de lignes, affiché à côté de l'intitulé : replié, le bloc doit
    // quand même dire ce qu'il contient, sinon on l'ouvre pour rien.
    'nombre' => null,
    'replie' => false,
])

{{--
    Le repli est décidé côté serveur, et non masqué côté navigateur.

    Deux tentatives ont échoué avant celle-ci. Avec x-show d'Alpine, le bouton
    réagissait — l'état changeait et se mémorisait — mais le contenu restait
    affiché : l'effet ne se réévaluait plus, ce conteneur enveloppant un composant
    Livewire imbriqué dont l'initialisation rompt la liaison. Avec un <details>
    natif, le repli dépend d'un comportement du navigateur que je n'ai pas pu
    vérifier ici, un <details> nu ne se refermant pas dans ce panneau.

    Passer par Livewire lève les deux incertitudes, et se trouve être le meilleur
    choix de toute façon : un bloc replié n'est plus rendu du tout, donc ni sa
    requête ni son composant imbriqué ne sont construits. Replier les longs
    tableaux allège vraiment la page au lieu de seulement les cacher.
--}}
<div class="mb-4">
    {{-- Le bouton épouse son contenu au lieu de prendre toute la largeur : pleine
         largeur, un clic n'importe où dans la ligne repliait la section, et le mot
         « Masquer » se retrouvait à un mètre du titre qu'il commande. --}}
    <button type="button"
            wire:click="basculerBloc('{{ $cle }}')"
            aria-expanded="{{ $replie ? 'false' : 'true' }}"
            class="inline-flex items-center gap-2 text-start text-xs text-gray-500 uppercase mb-2 px-2 py-1 -ms-2 rounded hover:text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primaire-500">
        {{-- width/height en attributs, et pas seulement en classes : si le CSS
             compilé devance d'une version le gabarit, une icône sans taille
             s'étire à son conteneur et dévore la page. --}}
        {{-- La rotation porte sur le <span> et non sur le <svg> : lors du
             rafraîchissement, Livewire ne met pas à jour l'attribut class d'un
             élément SVG, si bien que la flèche restait tournée une fois la section
             repliée alors que le libellé et le contenu, eux, suivaient. --}}
        <span class="inline-flex shrink-0 transition-transform rtl:-scale-x-100 {{ $replie ? '' : 'rotate-90' }}">
            <svg width="14" height="14" class="w-3.5 h-3.5"
                 viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 0 1 .02-1.06L11.168 10 7.23 6.29a.75.75 0 1 1 1.04-1.08l4.5 4.25a.75.75 0 0 1 0 1.08l-4.5 4.25a.75.75 0 0 1-1.06-.02Z" clip-rule="evenodd" />
            </svg>
        </span>

        <span>{{ $titre }}</span>

        @if ($nombre !== null)
            <span class="text-gray-400 normal-case">({{ \App\Support\Montant::format($nombre) }})</span>
        @endif

        <span class="normal-case text-gray-400">{{ $replie ? __('Afficher') : __('Masquer') }}</span>
    </button>

    @unless ($replie)
        {{ $slot }}
    @endunless
</div>
