@props([
    // Ce que le groupe rassemble : une année, une famille de documents.
    'titre',
    // Ce qu'il en contient, dit en clair : « — 6 relevé(s) ».
    'detail' => null,
    'colonnes' => 4,
])

{{--
    L'en-tête d'un groupe de lignes, et son bouton de repli.

    Le repli est tenu par Alpine, et non par Livewire comme celui de
    x-bloc-repliable. La raison de ce dernier ne vaut pas ici : il enveloppe un
    composant Livewire imbriqué, dont l'initialisation rompait la liaison
    d'Alpine. Ces lignes-ci sont du balisage ordinaire, déjà rendu.

    Chaque groupe porte son propre x-data, posé sur son <tbody> — un tableau en
    accepte plusieurs, et c'est ce qui permet de replier un groupe sans toucher
    aux autres.
--}}
<tr class="bg-primaire-50/60">
    <td colspan="{{ $colonnes }}" class="px-4 py-2">
        <button type="button"
                @click="ouvert = ! ouvert"
                :aria-expanded="ouvert ? 'true' : 'false'"
                class="-ms-2 inline-flex items-center gap-2 rounded px-2 py-1 text-start text-sm text-primaire-800 transition hover:bg-primaire-100/60 focus:outline-none focus:ring-2 focus:ring-primaire-500">
            {{-- La rotation porte sur le <span> : Livewire ne met pas à jour
                 l'attribut class d'un <svg> lors d'un rafraîchissement, et la
                 flèche resterait tournée alors que le contenu, lui, suit. --}}
            <span class="inline-flex shrink-0 transition-transform rtl:-scale-x-100"
                  :class="ouvert && 'rotate-90'">
                {{-- width/height en attributs autant qu'en classes : si le CSS
                     compilé devance le gabarit, une icône sans taille s'étire à
                     son conteneur et dévore la page. --}}
                <svg width="14" height="14" class="h-3.5 w-3.5"
                     viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 0 1 .02-1.06L11.168 10 7.23 6.29a.75.75 0 1 1 1.04-1.08l4.5 4.25a.75.75 0 0 1 0 1.08l-4.5 4.25a.75.75 0 0 1-1.06-.02Z" clip-rule="evenodd" />
                </svg>
            </span>

            <span class="font-bold">{{ $titre }}</span>

            @if ($detail)
                <span class="text-primaire-700/70">— {{ $detail }}</span>
            @endif

            <span class="text-primaire-700/70"
                  x-text="ouvert ? @js(__('Masquer')) : @js(__('Afficher'))"></span>
        </button>
    </td>
</tr>
