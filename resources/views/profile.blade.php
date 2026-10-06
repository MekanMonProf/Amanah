<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __("Profil") }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-champ">
                <div class="max-w-xl">
                    <livewire:profile.update-profile-information-form />
                </div>
            </div>

            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-champ">
                <div class="max-w-xl">
                    <livewire:profile.update-password-form />
                </div>
            </div>

            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-champ">
                <div class="max-w-xl">
                    <livewire:auth.gerer-deux-fa />
                </div>
            </div>

            {{-- La suppression de compte par l'intéressé a été retirée le 26/09/2026.
                 Elle venait du gabarit Breeze et ne convenait pas ici : un compte est
                 créé par un gestionnaire, et l'effacer ne supprimait rien de ce que
                 l'écran promettait — la clé étrangère est en ON DELETE SET NULL, donc
                 les parts, le solde et l'historique de l'actionnaire restaient en base.
                 Seul l'accès disparaissait, sans trace au journal d'audit. La
                 désactivation d'un compte se fait depuis la fiche, par un gestionnaire. --}}
        </div>
    </div>
</x-app-layout>
