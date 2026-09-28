<div class="max-w-6xl">
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-gray-800">{{ __("Paramétrage") }}</h1>
        <p class="text-sm text-gray-500">{{ __("Ce que chaque rôle peut faire, et ce qu'un dossier doit contenir.") }}</p>
    </div>

    @if (session('succes_parametrage'))
        <div class="mb-4 text-sm text-emerald-800 bg-emerald-50 border border-emerald-200 rounded-lg px-4 py-3">
            ✓ {{ session('succes_parametrage') }}
        </div>
    @endif

    @if (session('info_parametrage'))
        <div class="mb-4 text-sm text-gray-700 bg-gray-50 border border-gray-200 rounded-lg px-4 py-3">
            {{ session('info_parametrage') }}
        </div>
    @endif

    <div class="flex flex-wrap gap-2 mb-5 border-b">
        <button type="button" wire:click="changerOnglet('droits')"
                class="px-4 py-2 text-sm -mb-px border-b-2 {{ $onglet === 'droits' ? 'border-emerald-600 text-emerald-700 font-medium' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
            {{ __("Accès aux modules") }}
        </button>
        <button type="button" wire:click="changerOnglet('comptes')"
                class="px-4 py-2 text-sm -mb-px border-b-2 {{ $onglet === 'comptes' ? 'border-emerald-600 text-emerald-700 font-medium' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
            {{ __("Comptes utilisateurs") }}
        </button>
        <button type="button" wire:click="changerOnglet('champs')"
                class="px-4 py-2 text-sm -mb-px border-b-2 {{ $onglet === 'champs' ? 'border-emerald-600 text-emerald-700 font-medium' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
            {{ __("Champs du dossier") }}
        </button>
    </div>

    @if ($onglet === 'droits')
        <form wire:submit="enregistrerDroits" class="bg-white border rounded-lg shadow-sm">
            <div class="p-5 border-b">
                <h2 class="font-semibold text-gray-800">{{ __("Accès aux modules") }}</h2>
                <p class="mt-1 text-sm text-gray-500">
                    {{ __("« Lecture » donne le droit de consulter, « Écriture » celui de modifier. Un rôle sans accès ne voit pas l'entrée dans le menu et ne peut pas atteindre la page, même par son adresse.") }}
                </p>
            </div>

            {{-- Le tableau déborde sur un écran étroit : il défile dans son propre
                 cadre plutôt que de pousser la page entière de côté. --}}
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 text-xs text-gray-500 uppercase">
                            <th class="text-start font-medium p-3 min-w-56">{{ __("Module") }}</th>
                            @foreach ($roles as $role)
                                <th class="text-start font-medium p-3 min-w-36">{{ __(\App\Support\Modules::libelleRole($role)) }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach ($modules as $code => $module)
                            <tr>
                                <td class="p-3 align-top">
                                    <div class="font-medium text-gray-800">{{ __($module['libelle']) }}</div>
                                    <div class="text-xs text-gray-500">{{ __($module['portee']) }}</div>
                                </td>
                                @foreach ($roles as $role)
                                    <td class="p-3 align-top">
                                        @if ($this->estFigee($role, $code))
                                            <div class="text-xs text-gray-500 border border-dashed rounded px-2 py-2">
                                                {{ __("Écriture") }}
                                                <div class="mt-0.5">{{ __("non modifiable") }}</div>
                                            </div>
                                        @else
                                            <select wire:model="grille.{{ $role }}.{{ $code }}"
                                                    class="w-full border rounded px-2 py-1.5 text-sm">
                                                @foreach (\App\Support\Modules::niveauxPossibles($code) as $niveau)
                                                    <option value="{{ $niveau }}">{{ __(\App\Support\Modules::libelleNiveau($niveau)) }}</option>
                                                @endforeach
                                            </select>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="p-5 border-t flex flex-wrap items-center gap-3">
                <button type="submit" class="text-sm text-white bg-emerald-600 rounded-lg px-4 py-2 hover:bg-emerald-700">
                    {{ __("Enregistrer les droits") }}
                </button>
                <span class="text-xs text-gray-500">
                    {{ __("L'administrateur garde toujours l'accès au paramétrage : sans cela, une grille mal réglée ne se rattraperait plus depuis l'application.") }}
                </span>
            </div>
        </form>
    @elseif ($onglet === 'comptes')
        <livewire:parametrage.comptes-utilisateurs />
    @else
        <div class="bg-white border rounded-lg shadow-sm p-8 text-center text-gray-400">
            {{ __("Les champs du dossier se règlent bientôt ici.") }}
        </div>
    @endif
</div>
