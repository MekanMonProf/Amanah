<div class="p-4 sm:p-6 lg:p-8 max-w-3xl">
    <div class="mb-6">
        <x-surtitre>{{ __("Aide") }}</x-surtitre>
        <h1 class="text-2xl sm:text-3xl font-bold text-gray-900">{{ __("Contacter le support") }}</h1>
        <p class="text-sm text-gray-500">
            @if ($destinataire['estGestionnaire'] && $destinataire['nom'])
                {{ __("Votre demande part vers :nom, votre gestionnaire.", ['nom' => $destinataire['nom']]) }}
            @else
                {{ __("Votre demande est enregistrée, puis vous pouvez prévenir le support.") }}
            @endif
        </p>
    </div>

    @if ($demande)
        {{-- La demande existe déjà en base : ce bandeau ne demande pas de
             l'envoyer, il propose de prévenir. Si personne n'appuie, la
             demande reste. --}}
        <div class="mb-6 overflow-hidden rounded-carte bg-nuit-900">
            <div class="p-6">
                <x-surtitre class="text-white/40">{{ __("Demande enregistrée") }}</x-surtitre>
                <p class="mt-1 text-lg font-bold text-white">
                    {{ __("Demande n° :numero", ['numero' => $demande->id]) }}
                </p>
                <p class="mt-1 text-sm text-white/60">{{ $demande->sujet }}</p>
            </div>

            <div class="flex flex-wrap gap-2 border-t border-white/10 px-6 py-4">
                @if ($lienWhatsapp)
                    <a href="{{ $lienWhatsapp }}" target="_blank" rel="noopener"
                       class="rounded-champ bg-white px-4 py-2.5 text-sm font-semibold text-nuit-900 transition hover:bg-primaire-50">
                        {{ __("Prévenir par WhatsApp") }}
                    </a>
                @endif
                @if ($lienEmail)
                    <a href="{{ $lienEmail }}"
                       class="rounded-champ border border-white/30 px-4 py-2.5 text-sm text-white transition hover:bg-white/10">
                        {{ __("Prévenir par email") }}
                    </a>
                @endif
                <button type="button" wire:click="nouvelleDemande"
                        class="rounded-champ border border-white/30 px-4 py-2.5 text-sm text-white transition hover:bg-white/10">
                    {{ __("Écrire une autre demande") }}
                </button>
            </div>

            @unless ($lienWhatsapp || $lienEmail)
                <div class="border-t border-white/10 px-6 py-4 text-sm text-white/60">
                    {{ __("Aucun canal n'est encore renseigné : votre demande est enregistrée et sera relevée depuis le paramétrage.") }}
                </div>
            @endunless
        </div>
    @else
        <form wire:submit="envoyer" class="rounded-carte border border-gray-200 bg-white p-6 sm:p-8">
            <div>
                <x-input-label for="categorie" :value="__('De quoi s\'agit-il ?')" />
                <select id="categorie" wire:model="categorie"
                        class="mt-1 block w-full rounded-champ border-gray-300 px-3 py-2.5 text-sm focus:border-primaire-600 focus:ring-primaire-600">
                    @foreach (\App\Models\DemandeSupport::CATEGORIES as $code => $libelle)
                        <option value="{{ $code }}">{{ __($libelle) }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('categorie')" class="mt-2" />
            </div>

            <div class="mt-4">
                <x-input-label for="sujet" :value="__('En une phrase')" />
                <x-text-input wire:model="sujet" id="sujet" type="text" class="mt-1 block w-full"
                              placeholder="{{ __('Le dividende de septembre ne s\'affiche pas') }}" />
                <x-input-error :messages="$errors->get('sujet')" class="mt-2" />
            </div>

            <div class="mt-4">
                <x-input-label for="message" :value="__('Ce qui s\'est passé')" />
                <textarea id="message" wire:model="message" rows="6"
                          class="mt-1 block w-full rounded-champ border-gray-300 px-3 py-2.5 text-sm focus:border-primaire-600 focus:ring-primaire-600"
                          placeholder="{{ __('Ce que vous faisiez, ce que vous attendiez, ce qui est arrivé.') }}"></textarea>
                <x-input-error :messages="$errors->get('message')" class="mt-2" />
            </div>

            @if ($origine)
                <p class="mt-3 text-xs text-gray-400">
                    {{ __("L'écran d'où vous venez (:ecran) sera joint à la demande.", ['ecran' => $origine]) }}
                </p>
            @endif

            <x-primary-button class="mt-6 w-full justify-center sm:w-auto">
                {{ __("Envoyer la demande") }}
            </x-primary-button>
        </form>
    @endif

    @if ($destinataire['numero'] || $destinataire['email'])
        <div class="mt-6 rounded-carte border border-gray-200 bg-white p-5 text-sm">
            <x-surtitre>{{ __("Joindre directement") }}</x-surtitre>
            <dl class="mt-2 space-y-1 text-gray-700">
                @if ($destinataire['nom'])
                    <div><dt class="inline text-gray-500">{{ __("Interlocuteur") }} :</dt> <dd class="inline">{{ $destinataire['nom'] }}</dd></div>
                @endif
                @if ($destinataire['numero'])
                    <div><dt class="inline text-gray-500">{{ __("Téléphone") }} :</dt> <dd class="inline">{{ $destinataire['numero'] }}</dd></div>
                @endif
                @if ($destinataire['email'])
                    <div><dt class="inline text-gray-500">{{ __("Email") }} :</dt> <dd class="inline">{{ $destinataire['email'] }}</dd></div>
                @endif
                {{-- Les horaires ne valent que pour le support de la maison : un
                     gestionnaire répond quand il répond, et annoncer des heures
                     pour lui serait une promesse qu'il n'a pas faite. --}}
                @if (! $destinataire['estGestionnaire'] && $horaires)
                    <div><dt class="inline text-gray-500">{{ __("Horaires") }} :</dt> <dd class="inline">{{ $horaires }}</dd></div>
                @endif
            </dl>
        </div>
    @endif

    @if ($mesDemandes->isNotEmpty())
        <div class="mt-8 border-t border-gray-200 pt-6">
            <x-surtitre>{{ __("Mes dernières demandes") }}</x-surtitre>
            <ul class="mt-2 divide-y">
                @foreach ($mesDemandes as $precedente)
                    <li class="flex flex-wrap items-center justify-between gap-2 py-3">
                        <span class="min-w-0">
                            <span class="block text-sm text-gray-800">{{ $precedente->sujet }}</span>
                            <span class="block text-xs text-gray-400">
                                {{ $precedente->created_at->format('d/m/Y') }} · {{ __($precedente->libelleCategorie()) }}
                            </span>
                        </span>
                        <span class="shrink-0 rounded-full px-2 py-1 text-xs {{ $precedente->estOuverte() ? 'bg-or-50 text-or-700' : 'bg-primaire-100 text-primaire-700' }}">
                            {{ __($precedente->libelleStatut()) }}
                        </span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
