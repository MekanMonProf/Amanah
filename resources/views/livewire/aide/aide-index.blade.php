<div class="max-w-4xl">
    <div class="mb-6">
        <x-surtitre>{{ __("Aide") }}</x-surtitre>
        <h1 class="text-2xl sm:text-3xl font-bold text-gray-900">{{ __("Mode d'emploi") }}</h1>
        <p class="text-sm text-gray-500">
            {{ __("Chaque écran de l'application, expliqué pas à pas.") }}
        </p>
    </div>

    <div class="mb-6">
        <label for="recherche-aide" class="sr-only">{{ __("Rechercher dans le mode d'emploi") }}</label>
        <input id="recherche-aide" type="search" wire:model.live.debounce.250ms="recherche"
               placeholder="{{ __('Rechercher un sujet…') }}"
               class="w-full rounded-champ border-gray-300 px-4 py-2.5 text-sm focus:border-primaire-600 focus:ring-primaire-600">
    </div>

    @if ($sujets === [])
        <div class="rounded-carte border border-gray-200 bg-white p-8 text-center text-gray-400">
            {{ __("Aucun sujet ne correspond à votre recherche.") }}
        </div>
    @else
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            @foreach ($sujets as $code => $sujet)
                <a href="{{ route('aide.sujet', $code) }}" wire:navigate
                   class="group flex gap-4 rounded-carte border border-gray-200 bg-white p-4 transition hover:border-primaire-300 hover:bg-primaire-50/40">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-champ bg-primaire-100 text-primaire-700">
                        <x-icone :nom="$sujet['icone']" class="h-5 w-5" />
                    </span>
                    <span class="min-w-0">
                        <span class="block font-semibold text-gray-900 group-hover:text-primaire-800">
                            {{ __($sujet['titre']) }}
                        </span>
                        <span class="mt-0.5 block text-sm text-gray-500">{{ __($sujet['resume']) }}</span>
                    </span>
                </a>
            @endforeach
        </div>
    @endif

    {{-- Le lien vers le support ferme le sommaire : on y arrive quand la
         réponse n'était pas dans les pages au-dessus. --}}
    <div class="mt-8 flex flex-col gap-3 rounded-carte bg-nuit-900 p-6 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <p class="font-semibold text-white">{{ __("Vous ne trouvez pas ?") }}</p>
            <p class="mt-1 text-sm text-white/60">
                {{ __("Écrivez-nous : votre demande est enregistrée et quelqu'un la reprend.") }}
            </p>
        </div>
        <a href="{{ route('support.contacter') }}" wire:navigate
           class="shrink-0 rounded-champ bg-white px-4 py-2.5 text-center text-sm font-semibold text-nuit-900 transition hover:bg-primaire-50">
            {{ __("Contacter le support") }}
        </a>
    </div>
</div>
