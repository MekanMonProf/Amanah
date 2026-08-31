<div class="p-4 sm:p-6 max-w-2xl">
    <a href="{{ route('investisseurs.show', $investisseur) }}" wire:navigate class="text-sm text-gray-500 hover:underline">← Retour au dossier</a>

    <h1 class="text-xl sm:text-2xl font-semibold text-gray-800 mt-2 mb-1">Gestion de la succession</h1>
    <p class="text-sm text-gray-500 mb-6">
        {{ $investisseur->nom }} {{ $investisseur->prenom }} ({{ $investisseur->identifiant_externe }}) —
        décédé(e) le {{ $investisseur->date_deces?->format('d/m/Y') }}
        @if ($investisseur->piece_acte_deces_path)
            · <a href="{{ \Illuminate\Support\Facades\Storage::url($investisseur->piece_acte_deces_path) }}" target="_blank" class="text-emerald-700 hover:underline">Voir l'acte de décès</a>
        @endif
    </p>

    <div class="bg-purple-50 border border-purple-200 rounded-lg p-4 mb-3 text-sm text-purple-800">
        🕌 <strong>Compte(s) Waqf :</strong> le capital est automatiquement redirigé vers l'œuvre caritative
        <strong>Waqf Dolel Xamxam</strong>, jamais vers le mandataire — conformément au principe d'inaliénabilité du Waqf.
    </div>

    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6 text-sm text-blue-800">
        ℹ️ <strong>Compte(s) Commercial :</strong> la famille désigne un mandataire/procurataire unique, muni d'une
        procuration. Vous choisissez ensuite s'il devient investisseur ou si les avoirs lui sont payés directement.
    </div>

    @if ($investisseur->succession_reglee)
        <div class="bg-emerald-50 border border-emerald-200 rounded-lg p-4 mb-6 text-sm text-emerald-800">
            ✓ Succession réglée.
        </div>
    @endif

    @if ($resultatTransfert)
        <div class="bg-emerald-50 border border-emerald-200 rounded-lg p-4 mb-6">
            <p class="text-sm font-semibold text-emerald-800 mb-2">Règlement effectué :</p>
            <table class="w-full text-sm">
                <thead class="text-left text-emerald-700">
                    <tr><th class="py-1">Compte</th><th class="py-1">Destination</th><th class="py-1 text-right">Actions</th><th class="py-1 text-right">Montant</th><th class="py-1"></th></tr>
                </thead>
                <tbody class="divide-y divide-emerald-200">
                    @foreach ($resultatTransfert as $ligne)
                        <tr>
                            <td class="py-1 font-mono text-xs">{{ $ligne['compte'] }}</td>
                            <td class="py-1">{{ $ligne['destination'] }}</td>
                            <td class="py-1 text-right">{{ number_format($ligne['actions'], 0, ',', ' ') }}</td>
                            <td class="py-1 text-right">{{ number_format($ligne['montant'], 0, ',', ' ') }} CFA</td>
                            <td class="py-1 text-right"></td>
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
                <p class="text-sm font-semibold text-amber-800 mb-2">Versement(s) en attente :</p>
                @foreach ($comptesEnAttente as $compte)
                    <div class="flex justify-between items-center py-1">
                        <span class="text-sm">{{ $compte->numero_compte }} — {{ number_format($compte->solde(), 0, ',', ' ') }} CFA disponible</span>
                        <a href="{{ route('successions.paiement', $compte) }}" wire:navigate class="text-sm text-emerald-700 border border-emerald-700 rounded-lg px-3 py-1 hover:bg-emerald-50">
                            Verser au mandataire →
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
                    + Désigner le mandataire / procurataire
                </button>
            @else
                <form wire:submit="designerMandataire" class="bg-white border rounded-lg p-5 shadow-sm grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="text-sm text-gray-600">Nom</label>
                        <input type="text" wire:model="nom" class="w-full border rounded px-3 py-2">
                        @error('nom') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="text-sm text-gray-600">Prénom</label>
                        <input type="text" wire:model="prenom" class="w-full border rounded px-3 py-2">
                    </div>
                    <div>
                        <label class="text-sm text-gray-600">Téléphone</label>
                        <input type="text" wire:model="telephone" class="w-full border rounded px-3 py-2">
                    </div>
                    <div>
                        <label class="text-sm text-gray-600">Lien avec le défunt</label>
                        <input type="text" wire:model="lienParente" placeholder="Fils, Épouse, Avocat mandaté..." class="w-full border rounded px-3 py-2">
                        @error('lienParente') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="text-sm text-gray-600">Pièce d'identité (CNI) du mandataire — obligatoire</label>
                        <input type="file" wire:model="pieceIdentiteUpload" accept="image/*,.pdf" class="w-full border rounded px-3 py-2">
                        @error('pieceIdentiteUpload') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="text-sm text-gray-600">Certificat d'hérédité (ou acte de notoriété) — obligatoire</label>
                        <input type="file" wire:model="certificatHeritedeUpload" accept="image/*,.pdf" class="w-full border rounded px-3 py-2">
                        @error('certificatHeritedeUpload') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label class="text-sm text-gray-600">Procuration signée par la famille, habilitant ce mandataire — obligatoire</label>
                        <input type="file" wire:model="procurationUpload" accept="image/*,.pdf" class="w-full border rounded px-3 py-2">
                        @error('procurationUpload') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                    </div>
                    <div class="sm:col-span-2 flex gap-2">
                        <button type="submit" class="bg-emerald-700 text-white px-4 py-2 rounded-lg">Désigner ce mandataire</button>
                        <button type="button" wire:click="$set('afficherFormulaire', false)" class="text-sm text-gray-500 hover:underline">Annuler</button>
                    </div>
                </form>
            @endif
        @else
            <div class="bg-white border rounded-lg p-5 shadow-sm mb-4">
                <div class="flex justify-between items-start">
                    <div>
                        <div class="text-xs text-gray-500 uppercase mb-1">Mandataire désigné</div>
                        <div class="font-medium text-gray-800">{{ $this->mandataire->nom }} {{ $this->mandataire->prenom }}</div>
                        <div class="text-sm text-gray-500">{{ $this->mandataire->lien_parente }} @if($this->mandataire->telephone) · {{ $this->mandataire->telephone }} @endif</div>
                        <div class="mt-2 flex gap-3 text-xs">
                            <a href="{{ \Illuminate\Support\Facades\Storage::url($this->mandataire->piece_identite_path) }}" target="_blank" class="text-emerald-700 hover:underline">CNI →</a>
                            <a href="{{ \Illuminate\Support\Facades\Storage::url($this->mandataire->piece_certificat_heredite_path) }}" target="_blank" class="text-emerald-700 hover:underline">Certificat d'hérédité →</a>
                            <a href="{{ \Illuminate\Support\Facades\Storage::url($this->mandataire->piece_justificative_path) }}" target="_blank" class="text-emerald-700 hover:underline">Procuration →</a>
                        </div>
                    </div>
                    <button wire:click="retirerMandataire" wire:confirm="Retirer ce mandataire et en désigner un autre ?" class="text-xs text-red-600 hover:underline">
                        Changer
                    </button>
                </div>
            </div>

            <div class="bg-white border rounded-lg p-5 shadow-sm mb-4">
                <label class="text-sm font-medium text-gray-700 block mb-2">Mode de règlement du compte Commercial</label>
                <div class="flex gap-3">
                    <label class="flex-1 flex items-center gap-2 border rounded-lg px-3 py-2 cursor-pointer {{ $modeReglementCommercial === 'transfert' ? 'border-blue-500 bg-blue-50' : '' }}">
                        <input type="radio" wire:model.live="modeReglementCommercial" value="transfert" class="hidden">
                        <span class="text-sm">Transfert vers un compte investisseur</span>
                    </label>
                    <label class="flex-1 flex items-center gap-2 border rounded-lg px-3 py-2 cursor-pointer {{ $modeReglementCommercial === 'paiement' ? 'border-blue-500 bg-blue-50' : '' }}">
                        <input type="radio" wire:model.live="modeReglementCommercial" value="paiement" class="hidden">
                        <span class="text-sm">Paiement direct (liquidation complète)</span>
                    </label>
                </div>
                <p class="text-xs text-gray-400 mt-2">
                    @if ($modeReglementCommercial === 'paiement')
                        Les actions Commercial seront converties en argent, puis tout sera payé directement au mandataire — aucun dossier investisseur ne sera créé pour lui.
                    @else
                        Le mandataire devient investisseur (un dossier lui sera créé s'il n'en a pas déjà un) et reçoit les actions et le solde Commercial sur son propre compte.
                    @endif
                    Ce choix ne concerne que le Commercial — le Waqf suit toujours sa propre règle, ci-dessus.
                </p>
            </div>

            @if ($this->apercu)
                <div class="bg-white border rounded-lg p-5 shadow-sm mb-4">
                    <p class="text-sm font-medium text-gray-700 mb-3">Aperçu de ce qui sera effectué :</p>
                    <table class="w-full text-sm">
                        <thead class="text-left text-gray-500">
                            <tr>
                                <th class="py-1">Compte</th>
                                <th class="py-1">Destination</th>
                                <th class="py-1 text-right">Actions</th>
                                <th class="py-1 text-right">Montant actions</th>
                                <th class="py-1 text-right">Solde</th>
                                <th class="py-1 text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            @foreach ($this->apercu as $ligne)
                                <tr>
                                    <td class="py-1 font-mono text-xs">{{ $ligne['compte'] }}</td>
                                    <td class="py-1 text-xs">{{ $ligne['destination'] }}</td>
                                    <td class="py-1 text-right">{{ number_format($ligne['nb_actions'], 0, ',', ' ') }}</td>
                                    <td class="py-1 text-right">{{ number_format($ligne['montant_actions'], 0, ',', ' ') }}</td>
                                    <td class="py-1 text-right">{{ number_format($ligne['solde'], 0, ',', ' ') }}</td>
                                    <td class="py-1 text-right font-semibold">{{ number_format($ligne['total'], 0, ',', ' ') }} CFA</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            <button wire:click="reglerSuccession" wire:confirm="Régler définitivement cette succession selon l'aperçu ci-dessus ? Cette action ne peut pas être annulée depuis l'interface."
                    wire:loading.attr="disabled" wire:target="reglerSuccession"
                    class="bg-red-600 text-white px-5 py-2 rounded-lg hover:bg-red-700 disabled:opacity-50">
                <span wire:loading.remove wire:target="reglerSuccession">Régler la succession</span>
                <span wire:loading wire:target="reglerSuccession">Traitement en cours...</span>
            </button>
        @endif
    @endif
</div>
