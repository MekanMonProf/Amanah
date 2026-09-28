<div class="p-4 sm:p-6 max-w-2xl">
    <a href="{{ route('investisseurs.show', $investisseur) }}" wire:navigate class="text-sm text-gray-500 hover:underline">{{ __("← Retour au dossier") }}</a>

    <h1 class="text-xl sm:text-2xl font-semibold text-gray-800 mt-2 mb-1">{{ __("Gestion de la succession") }}</h1>
    <p class="text-sm text-gray-500 mb-6">
        {{ $investisseur->nom }} {{ $investisseur->prenom }} ({{ $investisseur->identifiant_externe }}) —
        {{ __('décédé(e) le :date', ['date' => $investisseur->date_deces?->format('d/m/Y')]) }}
        @if ($investisseur->piece_acte_deces_path)
            · <a href="{{ \Illuminate\Support\Facades\Storage::url($investisseur->piece_acte_deces_path) }}" target="_blank" class="text-emerald-700 hover:underline">{{ __("Voir l'acte de décès") }}</a>
        @endif
    </p>

    <div class="bg-purple-50 border border-purple-200 rounded-lg p-4 mb-3 text-sm text-purple-800">
        🕌 {!! __("<strong>Compte(s) Waqf :</strong> le capital est automatiquement redirigé vers l'œuvre caritative <strong>:waqf</strong>, jamais vers le mandataire — conformément au principe d'inaliénabilité du Waqf.", ['waqf' => \App\Models\Investisseur::NOM_WAQF_CARITATIF]) !!}
    </div>

    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6 text-sm text-blue-800">
        ℹ️ {!! __("<strong>Compte(s) Commercial :</strong> la famille désigne un mandataire/procurataire unique, muni d'une procuration. Vous choisissez ensuite s'il devient investisseur ou si les avoirs lui sont payés directement.") !!}
    </div>

    @if ($investisseur->succession_reglee)
        <div class="bg-emerald-50 border border-emerald-200 rounded-lg p-4 mb-6 text-sm text-emerald-800">
            {{ __("✓ Succession réglée.") }}
        </div>
    @endif

    @if ($resultatTransfert)
        <div class="bg-emerald-50 border border-emerald-200 rounded-lg p-4 mb-6">
            <p class="text-sm font-semibold text-emerald-800 mb-2">{{ __("Règlement effectué :") }}</p>
            <table class="w-full text-sm">
                <thead class="text-start text-emerald-700">
                    <tr><th class="py-1">{{ __("Compte") }}</th><th class="py-1">{{ __("Destination") }}</th><th class="py-1 text-end">{{ __("Actions") }}</th><th class="py-1 text-end">{{ __("Montant") }}</th><th class="py-1"></th></tr>
                </thead>
                <tbody class="divide-y divide-emerald-200">
                    @foreach ($resultatTransfert as $ligne)
                        <tr>
                            <td class="py-1 font-mono text-xs">{{ $ligne['compte'] }}</td>
                            <td class="py-1">{{ $ligne['destination'] }}</td>
                            <td class="py-1 text-end">{{ \App\Support\Montant::format($ligne['actions']) }}</td>
                            <td class="py-1 text-end">{{ \App\Support\Montant::format($ligne['montant']) }}&#8239;CFA</td>
                            <td class="py-1 text-end"></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if ($investisseur->succession_reglee)
        @php $comptesEnAttente = $investisseur->comptes()->where('categorie', 'commercial')->get()->filter(fn($c) => $c->solde() > 0); @endphp
        @if ($comptesEnAttente->isNotEmpty())
            <div class="bg-amber-50 border border-amber-200 rounded-lg p-4 mb-6">
                <p class="text-sm font-semibold text-amber-800 mb-2">{{ __("Versement(s) en attente :") }}</p>
                @foreach ($comptesEnAttente as $compte)
                    <div class="flex justify-between items-center py-1">
                        <span class="text-sm">{{ $compte->numero_compte }} — {{ \App\Support\Montant::format($compte->solde()) }}&#8239;{{ __("CFA disponible") }}</span>
                        <a href="{{ route('successions.paiement', $compte) }}" wire:navigate class="text-sm text-emerald-700 border border-emerald-700 rounded-lg px-3 py-1 hover:bg-emerald-50">
                            {{ __("Verser au mandataire →") }}
                        </a>
                    </div>
                @endforeach
            </div>
        @endif
    @endif

    @if (! $investisseur->succession_reglee)
        @if (! $this->mandataire)
            @if (! $afficherFormulaire)
                <button wire:click="$set('afficherFormulaire', true)" class="text-sm text-emerald-700 border border-emerald-700 rounded-lg px-4 py-2 hover:bg-emerald-50">
                    {{ __("+ Désigner le mandataire / procurataire") }}
                </button>
            @else
                <form wire:submit="designerMandataire" class="bg-white border rounded-lg p-5 shadow-sm grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="text-sm text-gray-600">{{ __("Nom") }}</label>
                        <input type="text" wire:model="nom" class="w-full border rounded px-3 py-2">
                        @error('nom') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="text-sm text-gray-600">{{ __("Prénom") }}</label>
                        <input type="text" wire:model="prenom" class="w-full border rounded px-3 py-2">
                    </div>
                    <div>
                        <label class="text-sm text-gray-600">{{ __("Téléphone") }}</label>
                        <input type="text" wire:model="telephone" class="w-full border rounded px-3 py-2">
                        <x-champ-whatsapp champ="whatsapp" drapeau="whatsappIdentique" :actif="$whatsappIdentique" />
                    </div>
                    <div>
                        <label class="text-sm text-gray-600">{{ __("Lien avec le défunt") }}</label>
                        <input type="text" wire:model="lienParente" placeholder="{{ __('Ex :') }} Fils, Épouse, Avocat mandaté..." class="w-full border rounded px-3 py-2">
                        @error('lienParente') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                    </div>
                    <div class="sm:col-span-2">
                        <p class="text-xs text-gray-500">
                            {{ __("Ces trois pièces peuvent être jointes plus tard : la désignation n'attend pas. Tant qu'elles manquent, le dossier reste signalé incomplet — ce qui est attendu se règle dans Paramétrage, onglet Champs du dossier.") }}
                        </p>
                    </div>

                    <div>
                        <label class="text-sm text-gray-600">{{ __("Pièce d'identité (CNI) du mandataire") }}</label>
                        <x-champ-fichier model="pieceIdentiteUpload" accept="image/*,.pdf" />
                        @error('pieceIdentiteUpload') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="text-sm text-gray-600">{{ __("Certificat d'hérédité (ou acte de notoriété)") }}</label>
                        <x-champ-fichier model="certificatHeritedeUpload" accept="image/*,.pdf" />
                        @error('certificatHeritedeUpload') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label class="text-sm text-gray-600">{{ __("Procuration signée par la famille, habilitant ce mandataire") }}</label>
                        <x-champ-fichier model="procurationUpload" accept="image/*,.pdf" />
                        @error('procurationUpload') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                    </div>
                    <div class="sm:col-span-2 flex gap-2">
                        <button type="submit" class="bg-emerald-700 text-white px-4 py-2 rounded-lg">{{ __("Désigner ce mandataire") }}</button>
                        <button type="button" wire:click="$set('afficherFormulaire', false)" class="text-sm text-gray-500 hover:underline">{{ __("Annuler") }}</button>
                    </div>
                </form>
            @endif
        @else
            <div class="bg-white border rounded-lg p-5 shadow-sm mb-4">
                <div class="flex justify-between items-start">
                    <div>
                        <div class="text-xs text-gray-500 uppercase mb-1">{{ __("Mandataire désigné") }}</div>
                        <div class="font-medium text-gray-800">{{ $this->mandataire->nom }} {{ $this->mandataire->prenom }}</div>
                        <div class="text-sm text-gray-500">{{ $this->mandataire->lien_parente }} @if($this->mandataire->telephone) · {{ $this->mandataire->telephone }} @endif</div>
                        <div class="mt-2 flex gap-3 text-xs">
                            <a href="{{ \Illuminate\Support\Facades\Storage::url($this->mandataire->piece_identite_path) }}" target="_blank" class="text-emerald-700 hover:underline">{{ __("CNI →") }}</a>
                            <a href="{{ \Illuminate\Support\Facades\Storage::url($this->mandataire->piece_certificat_heredite_path) }}" target="_blank" class="text-emerald-700 hover:underline">{{ __("Certificat d'hérédité →") }}</a>
                            <a href="{{ \Illuminate\Support\Facades\Storage::url($this->mandataire->piece_justificative_path) }}" target="_blank" class="text-emerald-700 hover:underline">{{ __("Procuration →") }}</a>
                        </div>
                    </div>
                    <button wire:click="retirerMandataire" wire:confirm="{{ __('Retirer ce mandataire et en désigner un autre ?') }}" class="text-xs text-red-600 hover:underline">
                        {{ __("Changer") }}
                    </button>
                </div>
            </div>

            <div class="bg-white border rounded-lg p-5 shadow-sm mb-4">
                <label class="text-sm font-medium text-gray-700 block mb-2">{{ __("Mode de règlement du compte Commercial") }}</label>
                <div class="flex gap-3">
                    <label class="flex-1 flex items-center gap-2 border rounded-lg px-3 py-2 cursor-pointer {{ $modeReglementCommercial === 'transfert' ? 'border-blue-500 bg-blue-50' : '' }}">
                        <input type="radio" wire:model.live="modeReglementCommercial" value="transfert" class="hidden">
                        <span class="text-sm">{{ __("Transfert vers un compte investisseur") }}</span>
                    </label>
                    <label class="flex-1 flex items-center gap-2 border rounded-lg px-3 py-2 cursor-pointer {{ $modeReglementCommercial === 'paiement' ? 'border-blue-500 bg-blue-50' : '' }}">
                        <input type="radio" wire:model.live="modeReglementCommercial" value="paiement" class="hidden">
                        <span class="text-sm">{{ __("Paiement direct (liquidation complète)") }}</span>
                    </label>
                </div>
                <p class="text-xs text-gray-400 mt-2">
                    @if ($modeReglementCommercial === 'paiement')
                        {{ __("Les actions Commercial seront converties en argent, puis tout sera payé directement au mandataire — aucun dossier investisseur ne sera créé pour lui.") }}
                    @else
                        {{ __("Le mandataire devient investisseur (un dossier lui sera créé s'il n'en a pas déjà un) et reçoit les actions et le solde Commercial sur son propre compte.") }}
                    @endif
                    {{ __("Ce choix ne concerne que le Commercial — le Waqf suit toujours sa propre règle, ci-dessus.") }}
                </p>
            </div>

            @if ($this->apercu)
                <div class="bg-white border rounded-lg p-5 shadow-sm mb-4">
                    <p class="text-sm font-medium text-gray-700 mb-3">{{ __("Aperçu de ce qui sera effectué :") }}</p>
                    <table class="w-full text-sm">
                        <thead class="text-start text-gray-500">
                            <tr>
                                <th class="py-1">{{ __("Compte") }}</th>
                                <th class="py-1">{{ __("Destination") }}</th>
                                <th class="py-1 text-end">{{ __("Actions") }}</th>
                                <th class="py-1 text-end">{{ __("Montant actions") }}</th>
                                <th class="py-1 text-end">{{ __("Solde") }}</th>
                                <th class="py-1 text-end">{{ __("Total") }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            @foreach ($this->apercu as $ligne)
                                <tr>
                                    <td class="py-1 font-mono text-xs">{{ $ligne['compte'] }}</td>
                                    <td class="py-1 text-xs">{{ $ligne['destination'] }}</td>
                                    <td class="py-1 text-end">{{ \App\Support\Montant::format($ligne['nb_actions']) }}</td>
                                    <td class="py-1 text-end">{{ \App\Support\Montant::format($ligne['montant_actions']) }}</td>
                                    <td class="py-1 text-end">{{ \App\Support\Montant::format($ligne['solde']) }}</td>
                                    <td class="py-1 text-end font-semibold">{{ \App\Support\Montant::format($ligne['total']) }}&#8239;CFA</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            <button wire:click="reglerSuccession" wire:confirm="{{ __("Régler définitivement cette succession selon l'aperçu ci-dessus ? Cette action ne peut pas être annulée depuis l'interface.") }}"
                    wire:loading.attr="disabled" wire:target="reglerSuccession"
                    class="bg-red-600 text-white px-5 py-2 rounded-lg hover:bg-red-700 disabled:opacity-50">
                <span wire:loading.remove wire:target="reglerSuccession">{{ __("Régler la succession") }}</span>
                <span wire:loading wire:target="reglerSuccession">{{ __("Traitement en cours...") }}</span>
            </button>
        @endif
    @endif
</div>
