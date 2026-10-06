@php($onglets = \App\Support\Navigation::onglets(auth()->user()))

@if ($onglets['entrees'] !== [])
    {{--
        Barre d'onglets du bas, sur écran étroit seulement.

        Elle ne remplace pas le tiroir : elle met à portée du pouce ce qu'on
        ouvre dix fois par jour, et renvoie le reste à « Plus », qui ouvre le
        menu complet. Au-delà de 1024 px la barre latérale reprend la main et
        celle-ci disparaît — deux navigations visibles en même temps feraient
        hésiter sur laquelle fait foi.

        Le `pb` ajoute la marge que réservent les téléphones sous leur barre
        système : sans elle, la dernière rangée d'icônes passe dessous.
    --}}
    <nav class="fixed inset-x-0 bottom-0 z-30 border-t border-gray-200 bg-white lg:hidden"
         style="padding-bottom: env(safe-area-inset-bottom, 0px);"
         {{-- Un libellé distinct de celui de la barre latérale : deux navigations
              nommées pareil, un lecteur d'écran ne saurait pas les départager. --}}
         aria-label="{{ __('Accès rapide') }}">
        <div class="flex items-stretch justify-around">
            @foreach ($onglets['entrees'] as $entree)
                @php($active = \App\Support\Navigation::estActive($entree))
                <a href="{{ route($entree['route']) }}" wire:navigate
                   @if ($active) aria-current="page" @endif
                   class="flex min-w-0 flex-1 flex-col items-center gap-1 px-1 py-2 text-center {{ $active ? 'text-primaire-700' : 'text-gray-500 hover:text-gray-700' }}">
                    <x-icone :nom="$entree['icone']" class="h-6 w-6 shrink-0" />
                    <span class="w-full truncate text-[11px] leading-tight">{{ __($entree['libelleCourt'] ?? $entree['libelle']) }}</span>
                </a>
            @endforeach

            @if ($onglets['davantage'])
                <button type="button" x-on:click="ouvert = true"
                        class="flex min-w-0 flex-1 flex-col items-center gap-1 px-1 py-2 text-center text-gray-500 hover:text-gray-700">
                    <svg class="h-6 w-6 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                    </svg>
                    <span class="w-full truncate text-[11px] leading-tight">{{ __('Plus') }}</span>
                </button>
            @endif
        </div>
    </nav>
@endif
