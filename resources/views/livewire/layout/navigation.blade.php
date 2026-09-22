<?php

use App\Livewire\Actions\Logout;
use App\Support\Navigation;
use Livewire\Volt\Component;

new class extends Component
{
    /**
     * Log the current user out of the application.
     */
    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect('/', navigate: true);
    }

    public function with(): array
    {
        return ['groupes' => Navigation::groupes(auth()->user())];
    }
}; ?>

{{--
    Barre latérale. Le repli (mode icônes) n'existe qu'à partir de `lg` : en
    dessous, la barre est un tiroir qui se superpose à la page, toujours déplié.
    `replie` et `ouvert` sont portés par la coquille de layouts/app.blade.php.
--}}
<aside class="barre-laterale fixed inset-y-0 start-0 z-40 flex w-64 flex-col border-e border-gray-200
              bg-white transition-[width,transform] duration-200 ease-out"
       :class="[
           replie ? 'lg:w-16 barre-repliee' : 'lg:w-64',
           ouvert && 'barre-ouverte',
       ]">

    {{-- En-tête : logo, et le bouton qui replie la barre sur les grands écrans --}}
    <div class="flex h-20 shrink-0 items-center gap-2 border-b border-gray-100 px-3">
        <a href="{{ auth()->user()->role === 'investisseur' ? route('portail.mon-compte') : route('dashboard') }}"
           wire:navigate class="flex min-w-0 flex-1 items-center justify-center">
            <x-application-logo class="logo-barre h-12 transition-[height] duration-200" />
        </a>

        <button type="button" x-on:click="replie = !replie"
                class="hidden shrink-0 rounded-lg p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-600 lg:block"
                :aria-label="replie ? @js(__('Déplier le menu')) : @js(__('Replier le menu'))"
                :title="replie ? @js(__('Déplier le menu')) : @js(__('Replier le menu'))">
            <svg class="h-5 w-5 rtl:-scale-x-100" :class="replie && 'rotate-180'" fill="none" viewBox="0 0 24 24"
                 stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M18.75 19.5l-7.5-7.5 7.5-7.5m-6 15L5.25 12l7.5-7.5" />
            </svg>
        </button>

        {{-- Fermeture du tiroir, sur mobile uniquement --}}
        <button type="button" x-on:click="ouvert = false"
                class="shrink-0 rounded-lg p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-600 lg:hidden"
                aria-label="{{ __("Fermer le menu") }}">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </div>

    <nav class="flex-1 space-y-6 overflow-y-auto px-3 py-4" aria-label="{{ __("Navigation principale") }}">
        @foreach ($groupes as $groupe)
            <div class="space-y-1">
                @if ($groupe['libelle'])
                    <p class="etiquette px-3 pb-1 text-xs font-semibold uppercase tracking-wider text-gray-400">
                        {{ __($groupe['libelle']) }}
                    </p>
                    {{-- En mode replié, un simple filet remplace l'intitulé du groupe --}}
                    <div class="separateur-groupe mx-3 hidden border-t border-gray-100 pb-1"></div>
                @endif

                @foreach ($groupe['entrees'] as $entree)
                    @php($actif = \App\Support\Navigation::estActive($entree))
                    <a href="{{ route($entree['route']) }}" wire:navigate
                       @class([
                           'lien-nav group flex items-center gap-3 rounded-lg px-3 py-2 text-sm transition',
                           'bg-emerald-50 font-semibold text-emerald-800' => $actif,
                           'font-medium text-gray-600 hover:bg-gray-100 hover:text-gray-900' => ! $actif,
                       ])
                       @if ($actif) aria-current="page" @endif
                       :title="replie ? @js(__($entree['libelle'])) : null">
                        <x-icone :nom="$entree['icone']"
                                 class="h-5 w-5 shrink-0 {{ $actif ? 'text-emerald-700' : 'text-gray-400 group-hover:text-gray-600' }}" />
                        <span class="etiquette truncate">{{ __($entree['libelle']) }}</span>
                    </a>
                @endforeach
            </div>
        @endforeach
    </nav>

    {{-- Carte utilisateur. Le menu s'ouvre vers le haut, la carte étant en bas. --}}
    <div class="shrink-0 border-t border-gray-100 p-3" x-data="{ menu: false }" x-on:click.outside="menu = false">
        <div class="relative">
            <div x-show="menu" x-cloak
                 x-transition:enter="transition ease-out duration-150"
                 x-transition:enter-start="opacity-0 translate-y-1"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 class="absolute bottom-full mb-2 w-48 rounded-md bg-white py-1 shadow-lg ring-1 ring-black ring-opacity-5"
                 x-on:click="menu = false">
                <x-dropdown-link :href="route('profile')" wire:navigate>
                    {{ __("Profil") }}
                </x-dropdown-link>

                <button wire:click="logout" class="w-full text-start">
                    <x-dropdown-link>
                        {{ __("Déconnexion") }}
                    </x-dropdown-link>
                </button>
            </div>

            <button type="button" x-on:click="menu = !menu"
                    class="lien-nav flex w-full items-center gap-3 rounded-lg px-2 py-2 text-start hover:bg-gray-100"
                    :title="replie ? @js(auth()->user()->nom . ' ' . auth()->user()->prenom) : null">
                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-emerald-700 text-xs font-semibold uppercase text-white">
                    {{ \Illuminate\Support\Str::substr(auth()->user()->prenom ?: auth()->user()->nom, 0, 1) }}{{ \Illuminate\Support\Str::substr(auth()->user()->nom, 0, 1) }}
                </span>
                <span class="etiquette min-w-0 flex-1">
                    <span class="block truncate text-sm font-medium text-gray-800">{{ auth()->user()->nom }} {{ auth()->user()->prenom }}</span>
                    <span class="block truncate text-xs text-gray-500">{{ auth()->user()->email }}</span>
                </span>
                <svg class="etiquette h-4 w-4 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 15.75l7.5-7.5 7.5 7.5" />
                </svg>
            </button>
        </div>
    </div>
</aside>
