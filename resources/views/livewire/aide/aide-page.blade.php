<div class="max-w-3xl">
    <a href="{{ route('aide.index') }}" wire:navigate class="text-sm text-gray-500 hover:underline">
        {{ __("← Retour au mode d'emploi") }}
    </a>

    <div class="mt-2 mb-6">
        <x-surtitre>{{ __("Aide") }}</x-surtitre>
        <h1 class="text-2xl sm:text-3xl font-bold text-gray-900">{{ __($definition['titre']) }}</h1>
        <p class="text-sm text-gray-500">{{ __($definition['resume']) }}</p>
    </div>

    @unless ($page['traduite'])
        {{-- Mieux vaut le dire que de laisser croire à un oubli : la page
             existe, elle n'est simplement pas encore traduite. --}}
        <div class="mb-6 rounded-champ border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-600">
            {{ __("Cette page n'est pas encore traduite ; elle s'affiche en français.") }}
        </div>
    @endunless

    @if ($video)
        <div class="mb-6 overflow-hidden rounded-carte border border-gray-200 bg-black">
            <div class="aspect-video">
                <iframe src="{{ $video['adresse'] }}" title="{{ __($definition['titre']) }}"
                        class="h-full w-full" loading="lazy"
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                        allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe>
            </div>

            {{-- Faute d'enregistrement dans la langue du lecteur, on sert celui
                 de la langue source et on le dit : on voit où l'on clique même
                 sans comprendre la bande son, mais mieux vaut le savoir avant
                 de lancer. --}}
            @unless ($video['dansLaLangue'])
                <p class="bg-gray-50 px-4 py-2 text-xs text-gray-500">
                    {{ __("Cette vidéo est commentée en :langue.", [
                        'langue' => __(\App\Support\Langue::DISPONIBLES[$video['langue']]['libelle']),
                    ]) }}
                </p>
            @endunless
        </div>
    @endif

    {{-- Le corps de la page. Les styles de prose sont posés ici plutôt que dans
         chaque fichier d'aide : les pages ne portent que du texte et de la
         structure, pour rester relisibles par quelqu'un qui n'écrit pas de code. --}}
    <article class="aide-prose rounded-carte border border-gray-200 bg-white p-6 sm:p-8">
        @include($page['vue'])
    </article>

    <div class="mt-6 flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap gap-2">
            @if ($voisins['precedent'])
                <a href="{{ route('aide.sujet', $voisins['precedent']) }}" wire:navigate
                   class="rounded-champ border border-gray-300 bg-white px-4 py-2 text-sm text-gray-700 transition hover:bg-gray-50">
                    {{ __("← :titre", ['titre' => __(\App\Support\Aide::titre($voisins['precedent']))]) }}
                </a>
            @endif
            @if ($voisins['suivant'])
                <a href="{{ route('aide.sujet', $voisins['suivant']) }}" wire:navigate
                   class="rounded-champ border border-gray-300 bg-white px-4 py-2 text-sm text-gray-700 transition hover:bg-gray-50">
                    {{ __(":titre →", ['titre' => __(\App\Support\Aide::titre($voisins['suivant']))]) }}
                </a>
            @endif
        </div>

        {{-- La demande part avec le sujet d'où elle vient : « où étiez-vous
             quand ça a coincé ? » est la question qu'on n'a plus à poser. --}}
        <a href="{{ route('support.contacter', ['depuis' => route('aide.sujet', $sujet, false)]) }}" wire:navigate
           class="text-sm text-primaire-700 hover:underline">
            {{ __("Cette page ne répond pas à ma question") }}
        </a>
    </div>
</div>
