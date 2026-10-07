<div class="p-4 sm:p-6">
    <x-surtitre>{{ __('Administration') }}</x-surtitre>
    <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 mb-1">{{ __("Importation de données") }}</h1>
    <p class="text-sm text-gray-500 mb-6">
        {{ __("Reprise de l'existant depuis un fichier Excel (.xlsx) ou CSV. Rien n'est enregistré avant que vous ayez validé le tableau de contrôle.") }}
    </p>

    <div class="bg-gray-50 border border-gray-200 rounded-champ p-3 mb-6 text-sm text-gray-600">
        {!! __("Ordre à respecter : <strong>gestionnaires</strong> → <strong>investisseurs</strong> → <strong>achats</strong> → <strong>écritures financières</strong>. Chaque étape s'appuie sur la précédente (un achat a besoin de son investisseur, un investisseur peut être rattaché à son gestionnaire).") !!}
    </div>

    <!-- Choix du type de données -->
    <div class="flex flex-wrap gap-2 mb-6">
        @foreach ($types as $cle => $libelle)
            <button type="button" wire:click="changerType('{{ $cle }}')"
                    class="px-4 py-2 rounded-champ text-sm border transition
                           {{ $type === $cle ? 'bg-primaire-700 text-white border-primaire-700' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50' }}">
                {{ $libelle }}
            </button>
        @endforeach
    </div>

    <!-- Étape 1 : le modèle -->
    <div class="bg-white border rounded-carte p-5 mb-6" x-data="{ colonnes: false }">
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3">
            <div>
                <h2 class="font-semibold text-gray-800">{{ __('1. Préparer le fichier — :type', ['type' => $types[$type]]) }}</h2>
                <p class="text-sm text-gray-500 mt-1">
                    {{ __("Le modèle contient les en-têtes attendus et une ligne d'exemple à remplacer par vos données.") }}
                </p>
            </div>
            <a href="{{ route('import.modele', ['type' => $type]) }}"
               class="bg-primaire-700 text-white px-4 py-2 rounded-champ text-sm hover:bg-primaire-800 text-center whitespace-nowrap">
                ⬇ {{ __("Télécharger le modèle CSV") }}
            </a>
        </div>

        <button type="button" @click="colonnes = ! colonnes" class="text-sm text-primaire-700 hover:underline mt-3">
            {{-- style="display:none" évite le clignotement avant l'initialisation d'Alpine --}}
            <span x-show="! colonnes">{{ __('Voir les :nombre colonnes attendues', ['nombre' => count($schema)]) }}</span>
            <span x-show="colonnes" style="display: none">{{ __("Masquer les colonnes") }}</span>
        </button>

        <div x-show="colonnes" style="display: none" class="mt-3 overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-gray-600">
                    <tr>
                        <th class="text-start px-3 py-2 font-medium">{{ __("Colonne") }}</th>
                        <th class="text-start px-3 py-2 font-medium">{{ __("Signification") }}</th>
                        <th class="text-start px-3 py-2 font-medium">{{ __("Exemple") }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @foreach ($schema as $colonne => $definition)
                        <tr>
                            <td class="px-3 py-2 align-top">
                                <span class="font-mono text-xs">{{ $colonne }}</span>
                                @if ($definition['obligatoire'])
                                    <span class="text-red-600 text-xs ms-1" title="{{ __('Colonne obligatoire') }}">*</span>
                                @endif
                            </td>
                            <td class="px-3 py-2 align-top text-gray-700">
                                {{ $definition['libelle'] }}
                                @isset($definition['aide'])
                                    <span class="block text-xs text-gray-500">{{ $definition['aide'] }}</span>
                                @endisset
                            </td>
                            <td class="px-3 py-2 align-top text-gray-500 font-mono text-xs">{{ $definition['exemple'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <p class="text-xs text-gray-500 mt-2">
                {{ __("* colonne obligatoire. L'ordre des colonnes n'a pas d'importance, les accents et majuscules des en-têtes non plus. Les intitulés courants sont reconnus (« Date de naissance », « Nb actions », « N° pièce »…). Dates acceptées : 31/12/2024 ou 2024-12-31. Montants : la virgule est le séparateur décimal, l'espace celui des milliers.") }}
            </p>
        </div>
    </div>

    <!-- Étape 2 : le fichier -->
    <div class="bg-white border rounded-carte p-5 mb-6">
        <h2 class="font-semibold text-gray-800 mb-3">{{ __("2. Déposer le fichier") }}</h2>

        <x-champ-fichier model="fichier" accept=".xlsx,.xlsm,.csv,.txt,.tsv" />

        @error('fichier') <span class="text-red-600 text-sm block mt-2">{{ $message }}</span> @enderror

        <div wire:loading wire:target="fichier, analyser" class="text-sm text-gray-500 mt-3">
            {{ __("Lecture et contrôle du fichier en cours…") }}
        </div>

        @if ($nomFichier && ! $erreurLecture && $resume)
            <p class="text-sm text-gray-500 mt-3">{{ __("Fichier analysé :") }}<span class="font-medium text-gray-700">{{ $nomFichier }}</span></p>
        @endif
    </div>

    @if ($erreurLecture)
        <div class="bg-red-50 border border-red-200 text-red-700 rounded-champ p-4 mb-6 text-sm">
            <strong>{{ __("Fichier non exploitable") }}</strong>
            <p class="mt-1">{{ $erreurLecture }}</p>
        </div>
    @endif

    <!-- Étape 3 : contrôle avant enregistrement -->
    @if ($resume)
        <div class="bg-white border rounded-carte p-5 mb-6">
            <h2 class="font-semibold text-gray-800 mb-4">{{ __("3. Contrôle avant enregistrement") }}</h2>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4">
                <div class="border rounded-champ p-3">
                    <div class="text-xs text-gray-500">{{ __("Lignes lues") }}</div>
                    <div class="text-xl font-semibold text-gray-800">{{ $resume['total'] }}</div>
                </div>
                <div class="border border-primaire-200 bg-primaire-50 rounded-champ p-3">
                    <div class="text-xs text-primaire-700">{{ __("À importer") }}</div>
                    <div class="text-xl font-semibold text-primaire-800">{{ $resume['valides'] }}</div>
                </div>
                <div class="border border-red-200 bg-red-50 rounded-champ p-3">
                    <div class="text-xs text-red-700">{{ __("En erreur") }}</div>
                    <div class="text-xl font-semibold text-red-800">{{ $resume['erreurs'] }}</div>
                </div>
                <div class="border border-amber-200 bg-amber-50 rounded-champ p-3">
                    <div class="text-xs text-amber-700">{{ __("Doublons ignorés") }}</div>
                    <div class="text-xl font-semibold text-amber-800">{{ $resume['doublons'] }}</div>
                </div>
            </div>

            @if ($colonnesInconnues)
                <div class="bg-amber-50 border border-amber-200 text-amber-800 rounded-champ p-3 mb-4 text-sm">
                    {{ __("Colonne(s) du fichier non reconnue(s), donc non importée(s) :") }}
                    <span class="font-mono text-xs">{{ implode(', ', $colonnesInconnues) }}</span>.
                    {{ __("Vérifiez l'orthographe des en-têtes si l'une d'elles devait être reprise.") }}
                </div>
            @endif

            <div class="overflow-x-auto border rounded-champ">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-gray-600">
                        <tr>
                            <th class="text-start px-3 py-2 font-medium">{{ __("Ligne") }}</th>
                            <th class="text-start px-3 py-2 font-medium">{{ __("État") }}</th>
                            @foreach ($colonnesApercu as $colonne)
                                <th class="text-start px-3 py-2 font-medium">{{ $schema[$colonne]['libelle'] }}</th>
                            @endforeach
                            <th class="text-start px-3 py-2 font-medium">{{ __("Remarque") }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach ($apercu as $ligne)
                            <tr class="{{ $ligne['statut'] === 'erreur' ? 'bg-red-50' : ($ligne['statut'] === 'doublon' ? 'bg-amber-50' : '') }}">
                                <td class="px-3 py-2 text-gray-500">{{ $ligne['numero'] }}</td>
                                <td class="px-3 py-2 whitespace-nowrap">
                                    @if ($ligne['statut'] === 'valide')
                                        <span class="px-2 py-0.5 text-xs font-semibold rounded bg-primaire-100 text-primaire-700">{{ __("À importer") }}</span>
                                    @elseif ($ligne['statut'] === 'doublon')
                                        <span class="px-2 py-0.5 text-xs font-semibold rounded bg-amber-100 text-amber-700">{{ __("Doublon") }}</span>
                                    @else
                                        <span class="px-2 py-0.5 text-xs font-semibold rounded bg-red-100 text-red-700">{{ __("Erreur") }}</span>
                                    @endif
                                </td>
                                @foreach ($ligne['cellules'] as $cellule)
                                    <td class="px-3 py-2 text-gray-700">{{ $cellule }}</td>
                                @endforeach
                                <td class="px-3 py-2 text-gray-600">
                                    @foreach ($ligne['messages'] as $message)
                                        <div class="{{ $ligne['statut'] === 'valide' ? 'text-gray-500' : '' }}">{{ $message }}</div>
                                    @endforeach
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($apercuTronque)
                <p class="text-xs text-gray-500 mt-2">
                    {{ __("Toutes les lignes à corriger sont affichées, complétées par un échantillon de lignes correctes.") }}
                    {{ __("Les autres lignes valides seront importées de la même façon.") }}
                </p>
            @endif

            @if ($type === 'achats')
                <div class="bg-gray-50 border border-gray-200 rounded-champ p-3 mt-4 text-sm text-gray-600">
                    {!! __("L'import n'enregistre que les achats. Les dividendes des mois passés se rattrapent ensuite en lançant <strong>:module</strong>, qui rejoue tout l'historique des barèmes déjà fixés.", ['module' => __('Dividendes → Calculer et distribuer')]) !!}
                </div>
            @endif

            @if ($type === 'ecritures')
                <div class="bg-gray-50 border border-gray-200 rounded-champ p-3 mt-4 text-sm text-gray-600">
                    {{ __("Les écritures sont enregistrées dans l'ordre des lignes du fichier, chacune recalculant le solde du compte. Aucun réinvestissement automatique n'est déclenché : les achats correspondants doivent être repris par l'import « Achats d'actions ».") }}
                </div>
            @endif

            <div class="mt-5 border-t pt-4">
                @if ($resume['erreurs'] > 0)
                    <label class="flex items-start gap-2 text-sm text-gray-700 mb-3">
                        <input type="checkbox" wire:model="confirmeIgnorerErreurs" class="mt-0.5 rounded border-gray-300 text-primaire-700">
                        <span>
                            {!! __("J'ai lu le tableau : importer les <strong>:valides</strong> ligne(s) valide(s) et ignorer les <strong>:erreurs</strong> ligne(s) en erreur.", ['valides' => $resume['valides'], 'erreurs' => $resume['erreurs']]) !!}
                            {{ __("Je pourrai corriger le fichier et réimporter ces lignes ensuite.") }}
                        </span>
                    </label>
                    @error('confirmeIgnorerErreurs') <span class="text-red-600 text-sm block mb-3">{{ $message }}</span> @enderror
                @endif

                <div class="flex flex-col sm:flex-row gap-2">
                    <button type="button" wire:click="importer" wire:loading.attr="disabled" wire:target="importer"
                            @disabled($resume['valides'] === 0)
                            class="bg-primaire-700 text-white px-5 py-2 rounded-champ hover:bg-primaire-800 disabled:opacity-50 disabled:cursor-not-allowed">
                        <span wire:loading.remove wire:target="importer">{{ __('Importer les :nombre ligne(s) valide(s)', ['nombre' => $resume['valides']]) }}</span>
                        <span wire:loading wire:target="importer">{{ __("Enregistrement en cours…") }}</span>
                    </button>
                    <button type="button" wire:click="reinitialiser" class="text-sm text-gray-500 hover:underline px-2">
                        {{ __("Annuler et changer de fichier") }}
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- Rapport final -->
    @if ($rapport)
        <div class="bg-primaire-50 border border-primaire-200 rounded-champ p-5 mb-6">
            <h2 class="font-semibold text-primaire-800 mb-2">{{ __('Import terminé — :type', ['type' => $types[$rapport['type']]]) }}</h2>
            <ul class="text-sm text-primaire-800 space-y-1">
                <li>✓ {!! __('<strong>:nombre</strong> ligne(s) enregistrée(s) depuis :fichier', ['nombre' => $rapport['importees'], 'fichier' => e($rapport['fichier'])]) !!}</li>
                @if ($rapport['doublons'] > 0)
                    <li>• {{ __(':nombre doublon(s) ignoré(s) — déjà présents en base', ['nombre' => $rapport['doublons']]) }}</li>
                @endif
                @if ($rapport['erreurs'] > 0)
                    <li>• {{ __(':nombre ligne(s) en erreur non importée(s) — corrigez-les dans le fichier puis relancez un import', ['nombre' => $rapport['erreurs']]) }}</li>
                @endif
            </ul>
            <p class="text-xs text-primaire-700 mt-3">{{ __("L'opération est tracée dans le journal d'audit.") }}</p>
        </div>
    @endif

    @if ($motsDePasse)
        <div class="bg-amber-50 border border-amber-200 rounded-champ p-5 mb-6">
            <h2 class="font-semibold text-amber-800 mb-1">{{ __("Mots de passe temporaires") }}</h2>
            <p class="text-sm text-amber-800 mb-3">
                {{ __("Aucun email n'a été envoyé (un import crée plusieurs comptes d'un coup).") }}
                <strong>{{ __("Copiez cette liste maintenant : elle ne sera plus affichée.") }}</strong>
                {{ __("Chaque gestionnaire devra changer son mot de passe à la première connexion.") }}
                {{ __("Pour envoyer l'email à quelqu'un en particulier, utilisez « Réinitialiser le mot de passe »") }}
                {{ __('sur la page') }} <a href="{{ route('gestionnaires.index') }}" class="underline" wire:navigate>{{ __("Gestionnaires") }}</a>.
            </p>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm bg-white rounded-champ">
                    <thead class="bg-amber-100 text-amber-900">
                        <tr>
                            <th class="text-start px-3 py-2 font-medium">{{ __("Nom") }}</th>
                            <th class="text-start px-3 py-2 font-medium">{{ __("Email") }}</th>
                            <th class="text-start px-3 py-2 font-medium">{{ __("Mot de passe temporaire") }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach ($motsDePasse as $compte)
                            <tr>
                                <td class="px-3 py-2 text-gray-700">{{ $compte['nom'] }}</td>
                                <td class="px-3 py-2 text-gray-700">{{ $compte['email'] }}</td>
                                <td class="px-3 py-2 font-mono text-gray-800">{{ $compte['mot_de_passe'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
