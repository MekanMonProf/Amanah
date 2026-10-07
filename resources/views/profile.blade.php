<x-app-layout>
    {{--
        Pas de slot « header » ici. Il venait du gabarit Breeze et posait une
        barre blanche à ombre portée au-dessus de la page, avec un titre plus
        petit que partout ailleurs : le profil était le seul écran de
        l'application à s'annoncer de cette façon. Il s'annonce maintenant
        comme les autres — surtitre, titre, sous-titre — et porte les mêmes
        marges.
    --}}
    <div class="p-4 sm:p-6 lg:p-8 max-w-3xl">
        <div class="mb-6">
            <x-surtitre>{{ __("Mon compte") }}</x-surtitre>
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900">{{ __("Profil") }}</h1>
            <p class="text-sm text-gray-500">
                {{ __("Vos informations, votre mot de passe et la double authentification.") }}
            </p>
        </div>

        <div class="space-y-4">
            <div class="bg-white border rounded-carte p-5 sm:p-6">
                <livewire:profile.update-profile-information-form />
            </div>

            <div class="bg-white border rounded-carte p-5 sm:p-6">
                <livewire:profile.update-password-form />
            </div>

            <div class="bg-white border rounded-carte p-5 sm:p-6">
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
</x-app-layout>
