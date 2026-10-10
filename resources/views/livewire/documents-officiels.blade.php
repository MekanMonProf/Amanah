<div class="p-4 sm:p-6 lg:p-8">
    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <x-surtitre>{{ __('Documents officiels') }}</x-surtitre>
            <h1 class="text-2xl font-bold text-gray-900 sm:text-3xl">{{ __("Publications de la direction") }}</h1>
            <p class="mt-1 text-sm text-gray-500">
                {{ __("Circulaires, rapports et procès-verbaux, consultables par tous les gestionnaires et investisseurs.") }}
            </p>
        </div>

        @if ($peutPublier)
            <button wire:click="{{ $afficherFormulaire ? '$set(\'afficherFormulaire\', false)' : 'ouvrirFormulaire' }}"
                    class="w-full whitespace-nowrap rounded-champ bg-primaire-700 px-4 py-2 text-white transition hover:bg-primaire-800 sm:w-auto">
                {{ $afficherFormulaire ? __('Annuler') : __('+ Publier un document') }}
            </button>
        @endif
    </div>

    @if (session('succes_document'))
        <div class="mb-4 rounded-champ border border-primaire-200 bg-primaire-50 p-3 text-sm text-primaire-700">
            {{ session('succes_document') }}
        </div>
    @endif

    @if ($peutPublier && $afficherFormulaire)
        <div class="mb-6 rounded-carte border bg-white p-5">
            <p class="mb-3 text-sm font-medium text-gray-700">{{ __("Publier un document") }}</p>

            {{-- Un document publié est visible de tous dès l'enregistrement : le
                 rappeler ici évite la publication faite pour voir. --}}
            <div class="mb-4 rounded-champ border border-or-300 bg-or-50 p-3 text-sm text-or-700">
                {{ __("Dès l'enregistrement, ce document sera visible et téléchargeable par tous les gestionnaires et tous les investisseurs.") }}
            </div>

            <form wire:submit="publier" class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="text-sm text-gray-600">{{ __("Titre") }}</label>
                    <input type="text" wire:model="titre" class="w-full rounded border px-3 py-2"
                           placeholder="{{ __('Ex : Rapport annuel 2026') }}">
                    @error('titre') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                </div>

                <div class="sm:col-span-2">
                    <label class="text-sm text-gray-600">{{ __("Description (optionnelle)") }}</label>
                    <textarea wire:model="description" rows="3" class="w-full rounded border px-3 py-2"
                              placeholder="{{ __('Ce que le document contient, pour qui hésite à l\'ouvrir.') }}"></textarea>
                    @error('description') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="text-sm text-gray-600">{{ __("Date du document (optionnelle)") }}</label>
                    <input type="date" wire:model="dateDocument" class="w-full rounded border px-3 py-2">
                    <p class="mt-1 text-xs text-gray-400">{{ __("La date que porte le document, si elle diffère de celle du dépôt.") }}</p>
                    @error('dateDocument') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="text-sm text-gray-600">{{ __("Fichier") }}</label>
                    <x-champ-fichier model="fichier" accept=".pdf,image/*,.doc,.docx,.xls,.xlsx" />
                    <p class="mt-1 text-xs text-gray-400">{{ __("PDF, image ou document bureautique, 10 Mo au plus.") }}</p>
                    @error('fichier') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                </div>

                <div class="flex gap-2 sm:col-span-2">
                    <button type="submit" wire:loading.attr="disabled" wire:target="publier,fichier"
                            class="w-full rounded-champ bg-primaire-700 px-4 py-2 text-white disabled:opacity-50 sm:w-auto">
                        <span wire:loading.remove wire:target="publier">{{ __("Publier") }}</span>
                        <span wire:loading wire:target="publier">{{ __("Publication en cours...") }}</span>
                    </button>
                    <button type="button" wire:click="$set('afficherFormulaire', false)" class="text-sm text-gray-500 hover:underline">
                        {{ __("Annuler") }}
                    </button>
                </div>
            </form>
        </div>
    @endif

    @if ($documents->isEmpty())
        <div class="rounded-carte border bg-white p-8 text-center text-gray-400">
            {{ __("Aucun document publié pour l'instant.") }}
        </div>
    @else
        <div class="overflow-hidden rounded-carte border border-gray-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[640px] text-sm">
                    <thead class="bg-gray-50 text-start text-xs uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-3 text-start font-semibold">{{ __("Document") }}</th>
                            <th class="px-4 py-3 text-start font-semibold">{{ __("Date") }}</th>
                            <th class="px-4 py-3 text-start font-semibold">{{ __("Publié par") }}</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-gray-100">
                        @foreach ($documents as $document)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3">
                                    <div class="font-semibold text-gray-900">{{ $document->titre }}</div>
                                    @if ($document->description)
                                        <div class="mt-0.5 max-w-prose text-xs text-gray-500">{{ $document->description }}</div>
                                    @endif
                                    <div class="mt-0.5 font-mono text-xs text-gray-400">
                                        {{ $document->extension() }} · {{ $document->tailleLisible() }}
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-gray-600">
                                    {{ ($document->date_document ?? $document->created_at)->format('d/m/Y') }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-gray-500">
                                    {{ $document->auteur ? trim($document->auteur->nom . ' ' . $document->auteur->prenom) : __('—') }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    <div class="flex items-center justify-end gap-4">
                                        <a href="{{ route('documents-officiels.ouvrir', $document) }}" target="_blank"
                                           title="{{ __('Ouvrir') }}"
                                           class="inline-flex items-center gap-1.5 text-sm text-gray-600 hover:text-primaire-700">
                                            <x-icone nom="oeil" class="h-4 w-4 shrink-0" />
                                            <span class="hidden xl:inline">{{ __("Ouvrir") }}</span>
                                        </a>

                                        <a href="{{ route('documents-officiels.ouvrir', [$document, 'telecharger' => 1]) }}"
                                           title="{{ __('Télécharger') }}"
                                           class="inline-flex items-center gap-1.5 text-sm text-gray-600 hover:text-primaire-700">
                                            <x-icone nom="telecharger" class="h-4 w-4 shrink-0" />
                                            <span class="hidden xl:inline">{{ __("Télécharger") }}</span>
                                        </a>

                                        @if ($peutPublier)
                                            <button wire:click="retirer({{ $document->id }})"
                                                    wire:confirm="{{ __('Retirer « :titre » ? Il ne sera plus visible par personne, et le fichier sera supprimé.', ['titre' => $document->titre]) }}"
                                                    title="{{ __('Retirer') }}"
                                                    class="inline-flex items-center gap-1.5 text-sm text-gray-600 hover:text-red-700">
                                                <x-icone nom="desactiver" class="h-4 w-4 shrink-0" />
                                                <span class="hidden xl:inline">{{ __("Retirer") }}</span>
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
