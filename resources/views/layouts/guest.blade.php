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
    <body class="font-sans text-gray-900 antialiased">
        {{--
            Deux colonnes sur grand écran : l'identité à gauche, le formulaire à
            droite. En RTL la grille s'inverse d'elle-même, le panneau sombre
            passe à droite — c'est bien le sens de lecture qui commande.
            Sous lg, le panneau redevient un simple bandeau d'en-tête.
        --}}
        <div class="min-h-screen lg:grid lg:grid-cols-[22rem_1fr] xl:grid-cols-[28rem_1fr]">
            <div class="flex flex-col justify-between bg-nuit-900 px-6 py-6 lg:px-10 lg:py-12">
                <div>
                    {{-- Le logo ne tient pas sur fond sombre : il garde ici le même
                         cartouche blanc que dans la barre latérale. --}}
                    <a href="{{ route('login') }}" wire:navigate
                       class="inline-flex items-center justify-center rounded-xl bg-white px-3 py-2">
                        <x-application-logo class="h-8 lg:h-10" />
                    </a>

                    <p class="mt-6 text-2xl font-bold text-white lg:mt-10 lg:text-3xl">
                        {{ config('app.name', 'Amanah') }}
                    </p>
                    <p class="mt-2 max-w-xs text-sm leading-relaxed text-white/60 lg:mt-3">
                        {{ __("Gestion des actionnaires et des dividendes.") }}
                    </p>
                </div>

                <p class="mt-8 hidden text-xs text-white/30 lg:block">
                    {{ __("Waqf Dolel Xamxam") }}
                </p>
            </div>

            <div class="flex flex-col items-center justify-center bg-gray-50 px-4 py-10 sm:px-6">
                <div class="w-full max-w-md rounded-carte border border-gray-200 bg-white p-6 shadow-sm sm:p-8">
                    {{ $slot }}
                </div>

                {{-- Choix de la langue avant connexion : il ne vit qu'en session, faute de
                     compte où l'enregistrer (voir App\Http\Middleware\AppliquerLangue).
                     Il reste du côté clair, où ses boutons se lisent. --}}
                <div class="mt-6">
                    <livewire:selecteur-langue />
                </div>
            </div>
        </div>
    </body>
</html>
