<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ \App\Support\Langue::sens() }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        {{--
            `replie` : barre latérale en mode icônes, retenue d'une page à l'autre.
            `ouvert` : tiroir de la barre latérale, sur les écrans étroits seulement.
        --}}
        <div class="min-h-screen bg-gray-50"
             x-data="{ replie: $persist(false).as('amanah-barre-repliee'), ouvert: false }"
             x-on:keydown.escape.window="ouvert = false">

            <!-- Voile du tiroir, sur mobile -->
            <div x-show="ouvert" x-cloak x-transition.opacity x-on:click="ouvert = false"
                 class="fixed inset-0 z-30 bg-gray-900/50 lg:hidden"></div>

            <livewire:layout.navigation />

            <div class="flex min-h-screen flex-col transition-[margin] duration-200 ease-out"
                 :class="replie ? 'lg:ms-16' : 'lg:ms-64'">

                <!-- Barre supérieure : ouverture du tiroir, section courante, langue -->
                <div class="sticky top-0 z-20 flex h-16 shrink-0 items-center gap-3 border-b border-gray-200 bg-white px-4 sm:px-6">
                    <button type="button" x-on:click="ouvert = true"
                            class="-ms-2 rounded-champ p-2 text-gray-500 hover:bg-gray-100 lg:hidden"
                            aria-label="{{ __("Ouvrir le menu") }}">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                        </svg>
                    </button>

                    @php($section = \App\Support\Navigation::sectionCourante(auth()->user()))
                    <span class="min-w-0 flex-1 truncate text-sm font-medium text-gray-500">
                        {{ $section ? __($section) : '' }}
                    </span>

                    <livewire:selecteur-langue />
                </div>

                @if (isset($header))
                    <header class="bg-white shadow">
                        <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                            {{ $header }}
                        </div>
                    </header>
                @endif

                {{-- La marge basse laisse la place a la barre d'onglets, qui flotte
                     au-dessus du contenu : sans elle, la derniere ligne d'un tableau
                     se retrouve dessous et personne ne la voit. --}}
                <main class="flex-1 pb-20 lg:pb-0">
                    {{ $slot }}
                </main>
            </div>

            <x-barre-onglets />
        </div>
    </body>
</html>
