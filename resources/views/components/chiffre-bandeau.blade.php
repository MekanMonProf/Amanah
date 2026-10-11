@props([
    'etiquette',
    // Déjà formaté. Pour un montant, passer le nombre formaté sans devise et
    // lever 'devise' : voir ci-dessous pourquoi les deux sont séparés.
    'valeur',
    'devise' => false,
    'accent' => false,
    'suffixe' => null,
])

{{--
    Un chiffre du bandeau sombre.

    La devise est un élément à part, séparé par une espace ordinaire — donc
    sécable. Montant::avecDevise les soude au contraire par une espace fine
    insécable, ce qui est juste dans une phrase mais faux ici : la cellule d'une
    grille peut être plus étroite que son contenu, et une chaîne insécable
    déborde alors sur la colonne voisine. C'est arrivé en production, où
    « 171 385 911 CFA » recouvrait le chiffre suivant.

    Les groupes de chiffres, eux, restent soudés : la coupure ne peut tomber
    qu'avant la devise, qui descend d'une ligne quand la place manque.
--}}
<p class="text-xs font-medium uppercase tracking-wide text-white/50">{{ $etiquette }}</p>
<p class="mt-1 text-xl font-bold sm:text-2xl {{ $accent ? 'text-primaire-400' : 'text-white' }}">
    <bdi class="whitespace-nowrap">{{ $valeur }}</bdi>@if ($devise) <bdi class="whitespace-nowrap">{{ \App\Support\Montant::DEVISE }}</bdi>@endif
    @if ($suffixe)
        <span class="text-sm font-normal text-white/50">{{ $suffixe }}</span>
    @endif
</p>
