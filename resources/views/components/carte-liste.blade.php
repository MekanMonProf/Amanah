@props(['titre', 'lien' => null, 'libelleLien' => null])

{{--
    Carte portant une liste. Le titre et, s'il y en a un, le lien d'action se
    tiennent sur la même ligne : l'action se lit avec le titre plutôt qu'au bas
    d'une liste dont on ne sait pas où elle finit.

    Le filet remplace l'ombre — l'ombre est gardée pour ce qui flotte vraiment.
--}}
<div {{ $attributes->merge(['class' => 'rounded-carte border border-gray-200 bg-white p-6']) }}>
    <div class="mb-2 flex items-center justify-between gap-4">
        <h2 class="text-base font-bold text-gray-900">{{ $titre }}</h2>

        @if ($lien)
            <a href="{{ $lien }}" wire:navigate
               class="shrink-0 text-sm font-semibold text-primaire-700 transition hover:text-primaire-800">
                {{ $libelleLien ?? __("Tout voir") }}
                <span aria-hidden="true" class="rtl:hidden">&rarr;</span>
                <span aria-hidden="true" class="hidden rtl:inline">&larr;</span>
            </a>
        @endif
    </div>

    <div class="divide-y divide-gray-100">
        {{ $slot }}
    </div>
</div>
