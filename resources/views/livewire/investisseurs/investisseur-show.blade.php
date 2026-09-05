<div class="p-4 sm:p-6">
    <a href="{{ route('investisseurs.index') }}" wire:navigate class="text-sm text-gray-500 hover:underline">← Retour à la liste</a>

    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-2 mt-2 mb-6">
        <div>
            <h1 class="text-xl sm:text-2xl font-semibold text-gray-800">
                {{ $investisseur->nom }} {{ $investisseur->prenom }}
            </h1>
            <p class="text-sm text-gray-500 font-mono">{{ $investisseur->identifiant_externe }}</p>
        </div>
        <span class="self-start px-3 py-1 text-sm rounded-full
            {{ $investisseur->statut === 'actif' ? 'bg-emerald-100 text-emerald-700' : ($investisseur->statut === 'decede' ? 'bg-gray-800 text-white' : 'bg-gray-100 text-gray-600') }}">
            {{ $investisseur->statut === 'decede' ? 'Décédé(e)' : $investisseur->statut }}
        </span>
    </div>

    @if ($investisseur->estDecede())
        <div class="bg-gray-800 text-white rounded-lg p-4 mb-6">
            <p class="text-sm font-semibold mb-1">
                ⚠️ Investisseur décédé le {{ $investisseur->date_deces?->format('d/m/Y') }} — comptes gelés.
            </p>
            <p class="text-xs text-gray-300 mb-3">
                Plus aucun achat, complément, paiement, don ou radiation n'est possible sur ses comptes.
                @if ($investisseur->succession_reglee)
                    La succession a déjà été réglée.
                @else
                    Gérez la succession pour répartir les avoirs entre les héritiers.
                @endif
            </p>
            <div class="flex gap-2 flex-wrap">
                @if ($investisseur->piece_acte_deces_path)
                    <a href="{{ \Illuminate\Support\Facades\Storage::url($investisseur->piece_acte_deces_path) }}" target="_blank" class="text-xs text-gray-300 border border-gray-600 rounded-lg px-3 py-1.5 hover:bg-gray-700">
                        Voir l'acte de décès
                    </a>
                @endif
                @if (in_array(auth()->user()->role, ['direction', 'administrateur']))
                    <a href="{{ route('successions.gerer', $investisseur) }}" wire:navigate class="text-xs text-white bg-emerald-700 rounded-lg px-3 py-1.5 hover:bg-emerald-800">
                        {{ $investisseur->succession_reglee ? 'Voir la succession' : 'Gérer la succession' }}
                    </a>
                @endif
            </div>
        </div>
    @endif

    @if (auth()->user()->role !== 'lecture' && ! $investisseur->estDecede())
        <a href="{{ route('investisseurs.modifier', $investisseur) }}" wire:navigate
           class="inline-block mb-6 text-sm text-emerald-700 border border-emerald-700 rounded-lg px-4 py-2 hover:bg-emerald-50">
            Modifier le dossier
        </a>
        @if (in_array(auth()->user()->role, ['direction', 'administrateur']))
            <a href="{{ route('deces.declarer', $investisseur) }}" wire:navigate
               class="inline-block mb-6 ml-2 text-sm text-gray-700 border border-gray-300 rounded-lg px-4 py-2 hover:bg-gray-50">
                Déclarer un décès
            </a>
            <button wire:click="basculerActifInvestisseur" wire:confirm="{{ $investisseur->statut === 'actif' ? 'Désactiver ce dossier ? L\'accès portail sera coupé s\'il en a un.' : 'Réactiver ce dossier ?' }}"
                    class="inline-block mb-6 ml-2 text-sm text-gray-700 border border-gray-300 rounded-lg px-4 py-2 hover:bg-gray-50">
                {{ $investisseur->statut === 'actif' ? 'Désactiver' : 'Réactiver' }}
            </button>
        @endif
    @endif

    <div class="bg-white border rounded-lg p-4 mb-6 inline-block">
        <form action="{{ route('investisseurs.releve', $investisseur) }}" method="GET" target="_blank" class="flex flex-wrap items-end gap-2">
            <div>
                <label class="text-xs text-gray-500 block mb-1">Du (optionnel)</label>
                <input type="date" name="date_debut" class="border rounded px-2 py-1.5 text-sm">
            </div>
            <div>
                <label class="text-xs text-gray-500 block mb-1">Au (optionnel)</label>
                <input type="date" name="date_fin" class="border rounded px-2 py-1.5 text-sm">
            </div>
            <button type="submit" class="text-sm text-gray-700 border border-gray-300 rounded-lg px-4 py-1.5 hover:bg-gray-50 whitespace-nowrap">
                📄 Télécharger le relevé (PDF)
            </button>
        </form>
        <p class="text-xs text-gray-400 mt-1">Laissez vide pour l'historique complet.</p>
    </div>

    @if (session('erreur_acces'))
        <div class="bg-red-50 border border-red-200 rounded-lg p-3 mb-4 text-sm text-red-700">
            {{ session('erreur_acces') }}
        </div>
    @endif

    @if (session('succes'))
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-lg p-3 mb-4 text-sm">
            {{ session('succes') }}
        </div>
    @endif

    @if ($dernierMotDePasseGenere)
        <div class="bg-amber-50 border border-amber-200 rounded-lg p-4 mb-6 text-sm text-amber-800">
            @if ($investisseur->user?->email)
                ✓ Identifiants de connexion envoyés par email à {{ $investisseur->user->email }}.
                <span class="text-xs text-amber-600">(mot de passe généré : <span class="font-mono">{{ $dernierMotDePasseGenere }}</span>, au cas où l'email n'arrive pas)</span>
            @else
                ✓ Accès créé — connexion par téléphone ({{ $investisseur->user?->telephone }}). Pas d'email sur ce dossier, transmettez le mot de passe directement à l'investisseur :
                <span class="text-sm font-mono font-semibold">{{ $dernierMotDePasseGenere }}</span>
            @endif
        </div>
    @endif

    @if (auth()->user()->role !== 'lecture' && ! $investisseur->estDecede())
        <div class="mb-6">
            @if ($investisseur->user_id)
                <span class="inline-block text-sm text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-lg px-4 py-2">
                    ✓ Accès portail activé ({{ $investisseur->user->email ?? $investisseur->user->telephone }})
                </span>
                <button wire:click="reinitialiserMotDePasse" wire:confirm="Générer un nouveau mot de passe temporaire pour {{ $investisseur->user->email ?? $investisseur->user->telephone }} ?"
                        class="ml-2 text-sm text-blue-600 border border-blue-300 rounded-lg px-4 py-2 hover:bg-blue-50">
                    Réinitialiser le mot de passe
                </button>
            @else
                <button wire:click="creerAcces" wire:confirm="Créer un accès de connexion pour cet investisseur ?"
                        class="text-sm text-blue-700 border border-blue-300 rounded-lg px-4 py-2 hover:bg-blue-50">
                    Créer un accès au portail investisseur
                </button>
            @endif
        </div>
    @elseif ($investisseur->user_id)
        <div class="mb-6">
            <span class="inline-block text-sm text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-lg px-4 py-2">
                ✓ Accès portail activé
            </span>
        </div>
    @endif

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 sm:gap-6 mb-4">
        <div class="bg-white border rounded-lg p-4">
            <div class="text-xs text-gray-500 uppercase">Téléphone</div>
            <div class="mt-1">{{ $investisseur->telephone ?: '—' }}</div>
        </div>
        <div class="bg-white border rounded-lg p-4">
            <div class="text-xs text-gray-500 uppercase">Email</div>
            <div class="mt-1">{{ $investisseur->email ?: '—' }}</div>
        </div>
        <div class="bg-white border rounded-lg p-4">
            <div class="text-xs text-gray-500 uppercase">Gestionnaire</div>
            <div class="mt-1 flex items-center justify-between gap-2">
                <span>{{ $investisseur->gestionnaire?->user?->nom ?? '— Non assigné' }}</span>
                @if (in_array(auth()->user()->role, ['direction', 'administrateur']) && ! $investisseur->estDecede())
                    <button wire:click="$toggle('afficherFormulaireTransfert')" class="text-xs text-emerald-700 hover:underline whitespace-nowrap">
                        Changer →
                    </button>
                @endif
            </div>
        </div>
        <div class="bg-white border rounded-lg p-4">
            <div class="text-xs text-gray-500 uppercase">Localisation</div>
            <div class="mt-1">{{ collect([$investisseur->ville, $investisseur->pays])->filter()->implode(', ') ?: '—' }}</div>
        </div>
        <div class="bg-white border rounded-lg p-4">
            <div class="text-xs text-gray-500 uppercase">Date de naissance</div>
            <div class="mt-1">{{ $investisseur->date_naissance?->format('d/m/Y') ?? '—' }}</div>
        </div>
        <div class="bg-white border rounded-lg p-4">
            <div class="text-xs text-gray-500 uppercase">Lieu de naissance</div>
            <div class="mt-1">{{ $investisseur->lieu_naissance ?: '—' }}</div>
        </div>
    </div>

    @if ($afficherFormulaireTransfert)
        <div class="bg-white border rounded-lg p-4 mb-4">
            <p class="text-sm font-medium text-gray-700 mb-2">Réassigner à un autre gestionnaire</p>
            <form wire:submit="transfererGestionnaire" class="flex flex-col sm:flex-row gap-2 sm:items-end">
                <div class="flex-1">
                    <label class="text-xs text-gray-500">Nouveau gestionnaire</label>
                    <select wire:model="nouveauGestionnaireId" class="w-full border rounded px-3 py-2 text-sm">
                        <option value="">— Choisir —</option>
                        @foreach ($gestionnaires as $g)
                            <option value="{{ $g->id }}">{{ $g->user->nom }} {{ $g->user->prenom }}</option>
                        @endforeach
                    </select>
                    @error('nouveauGestionnaireId') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                </div>
                <div class="flex-1">
                    <label class="text-xs text-gray-500">Motif (optionnel)</label>
                    <input type="text" wire:model="motifTransfert" class="w-full border rounded px-3 py-2 text-sm">
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="bg-emerald-700 text-white px-4 py-2 rounded-lg text-sm whitespace-nowrap">Transférer</button>
                    <button type="button" wire:click="$set('afficherFormulaireTransfert', false)" class="text-sm text-gray-500 hover:underline">Annuler</button>
                </div>
            </form>

            @if ($historiqueAffectations->isNotEmpty())
                <div class="mt-4 pt-4 border-t">
                    <p class="text-xs text-gray-500 uppercase mb-2">Historique des affectations</p>
                    <ul class="text-sm text-gray-600 space-y-1">
                        @foreach ($historiqueAffectations as $h)
                            <li>
                                {{ $h->date_transfert->format('d/m/Y') }} —
                                {{ $h->ancienGestionnaire?->user?->nom ?? 'Non assigné' }} → {{ $h->nouveauGestionnaire->user->nom }}
                                @if ($h->motif) <span class="text-gray-400">({{ $h->motif }})</span> @endif
                                @if ($h->effectuePar) <span class="text-gray-400">— par {{ $h->effectuePar->nom }}</span> @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    @endif

    @if ($investisseur->type_identification || $investisseur->numero_identification || $investisseur->piece_identite_path)
        <div class="bg-white border rounded-lg p-4 mb-4">
            <div class="text-xs text-gray-500 uppercase mb-2">Pièce d'identité</div>
            <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 text-sm">
                <div><span class="text-gray-500">Type :</span> {{ $investisseur->type_identification ?: '—' }}</div>
                <div><span class="text-gray-500">Numéro :</span> {{ $investisseur->numero_identification ?: '—' }}</div>
                <div><span class="text-gray-500">Délivrée le :</span> {{ $investisseur->date_delivrance_piece?->format('d/m/Y') ?? '—' }}</div>
                <div><span class="text-gray-500">Lieu :</span> {{ $investisseur->lieu_delivrance_piece ?: '—' }}</div>
                <div><span class="text-gray-500">Expire le :</span> {{ $investisseur->date_expiration_piece?->format('d/m/Y') ?? '—' }}</div>
            </div>
            @if ($investisseur->piece_identite_path)
                <a href="{{ \Illuminate\Support\Facades\Storage::url($investisseur->piece_identite_path) }}" target="_blank" class="inline-block mt-2 text-sm text-emerald-700 hover:underline">
                    Voir le scan du document →
                </a>
            @endif
        </div>
    @endif

    @if ($investisseur->convention_engagement_path)
        <div class="bg-white border rounded-lg p-4 mb-4">
            <div class="text-xs text-gray-500 uppercase mb-2">Convention d'engagement</div>
            <div class="text-sm">
                Signée le {{ $investisseur->date_signature_convention?->format('d/m/Y') ?? '—' }}
                <a href="{{ \Illuminate\Support\Facades\Storage::url($investisseur->convention_engagement_path) }}" target="_blank" class="ml-2 text-emerald-700 hover:underline">
                    Voir le document →
                </a>
            </div>
        </div>
    @endif

    @if ($investisseur->type_personne === 'morale' && $investisseur->raison_sociale)
        <div class="bg-white border rounded-lg p-4 mb-4">
            <div class="text-xs text-gray-500 uppercase mb-2">Entreprise</div>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-sm">
                <div><span class="text-gray-500">Raison sociale :</span> {{ $investisseur->raison_sociale }}</div>
                <div><span class="text-gray-500">RCCM :</span> {{ $investisseur->rccm ?: '—' }}</div>
                <div><span class="text-gray-500">NINEA :</span> {{ $investisseur->ninea ?: '—' }}</div>
                <div><span class="text-gray-500">Représentant :</span> {{ $investisseur->representant_legal_nom ?: '—' }}</div>
            </div>
        </div>
    @endif

    @if ($investisseur->beneficiaire_nom)
        <div class="bg-white border rounded-lg p-4 mb-8">
            <div class="text-xs text-gray-500 uppercase mb-2">Bénéficiaire désigné</div>
            <div class="text-sm">
                {{ $investisseur->beneficiaire_nom }}
                @if ($investisseur->beneficiaire_lien) ({{ $investisseur->beneficiaire_lien }}) @endif
                @if ($investisseur->beneficiaire_telephone) · {{ $investisseur->beneficiaire_telephone }} @endif
            </div>
        </div>
    @else
        <div class="mb-8"></div>
    @endif

    @if ($investisseur->notes_internes)
        <div class="bg-amber-50 border border-amber-200 rounded-lg p-4 mb-8">
            <div class="text-xs text-amber-700 uppercase mb-1">Notes internes</div>
            <div class="text-sm text-amber-900 whitespace-pre-line">{{ $investisseur->notes_internes }}</div>
        </div>
    @endif

    @if ($presents->isNotEmpty())
        <div class="bg-purple-50 border border-purple-200 rounded-lg p-4 mb-4">
            <h2 class="text-sm font-semibold text-purple-900 mb-1">Présents Waqf offerts par cet investisseur</h2>
            <p class="text-xs text-purple-700 mb-3">
                Payées par cet investisseur, versées au Waqf caritatif « {{ \App\Models\Investisseur::NOM_WAQF_CARITATIF }} » — elles ne figurent donc pas dans ses comptes ci-dessous.
            </p>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-xs text-purple-800 border-b border-purple-200">
                        <tr>
                            <th class="text-left py-1">Date</th>
                            <th class="text-left py-1">Bénéficiaire</th>
                            <th class="text-right py-1">Actions</th>
                            <th class="text-right py-1">Montant</th>
                            <th class="text-right py-1">Attestation</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($presents as $present)
                            <tr class="border-b border-purple-100 last:border-0">
                                <td class="py-1.5">{{ $present->date_achat->format('d/m/Y') }}</td>
                                <td class="py-1.5 font-medium text-gray-800">
                                    <span class="block text-xs font-normal text-purple-700">{{ $present->formulePresentMajuscule() }}</span>
                                    {{ $present->present_pour }}
                                    @if ($present->lien_avec_donateur)
                                        <span class="text-xs font-normal text-purple-700">({{ $present->lien_avec_donateur }})</span>
                                    @endif
                                </td>
                                <td class="py-1.5 text-right">{{ number_format($present->nombre_actions, 0, ',', ' ') }}</td>
                                <td class="py-1.5 text-right">{{ number_format($present->montant, 0, ',', ' ') }} CFA</td>
                                <td class="py-1.5 text-right">
                                    <a href="{{ route('achats.attestation', $present) }}" class="text-purple-700 hover:underline">PDF</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if ($presentsRecus->isNotEmpty())
        <div class="bg-purple-50 border border-purple-200 rounded-lg p-4 mb-4">
            <h2 class="text-sm font-semibold text-purple-900 mb-1">Présents Waqf reçus en son honneur</h2>
            <p class="text-xs text-purple-700 mb-3">Versées au Waqf caritatif en son nom : elles ne lui confèrent aucun droit patrimonial.</p>
            <ul class="text-sm space-y-1">
                @foreach ($presentsRecus as $present)
                    <li class="text-gray-800">
                        {{ $present->date_achat->format('d/m/Y') }} —
                        {{ number_format($present->nombre_actions, 0, ',', ' ') }} action(s)
                        offerte(s) par <strong>{{ $present->offertPar?->nom }} {{ $present->offertPar?->prenom }}</strong>@if ($present->lien_avec_donateur) <span class="text-gray-500">({{ mb_strtolower($present->lien_avec_donateur) }})</span>@endif
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3 mb-3">
        <h2 class="text-lg font-semibold text-gray-800">Comptes d'investissement</h2>
        @if (auth()->user()->role !== 'lecture' && ! $investisseur->estDecede())
            <a href="{{ route('achats.creer', $investisseur) }}" wire:navigate
               class="bg-emerald-700 text-white px-4 py-2 rounded-lg hover:bg-emerald-800 text-sm text-center w-full sm:w-auto">
                + Nouvel achat
            </a>
        @endif
    </div>

    @forelse ($comptesEnrichis as $item)
        <div class="bg-white border rounded-lg p-5 mb-4 shadow-sm">
            <div class="flex justify-between items-center mb-4">
                <div class="flex items-center gap-3">
                    <span class="px-2 py-1 text-xs font-semibold rounded {{ $item['compte']->categorie === 'commercial' ? 'bg-blue-100 text-blue-700' : 'bg-purple-100 text-purple-700' }}">
                        {{ ucfirst($item['compte']->categorie) }}
                    </span>
                    <span class="font-mono text-sm text-gray-500">{{ $item['compte']->numero_compte }}</span>
                </div>
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="text-xs px-2 py-1 rounded-full {{ $item['compte']->reinvestissement_auto ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-600' }}">
                        Réinvestissement auto : {{ $item['compte']->reinvestissement_auto ? 'Oui' : 'Non' }}
                    </span>
                    @if (auth()->user()->role !== 'lecture' && ! $investisseur->estDecede())
                        @if (! $item['compte']->reinvestissement_auto)
                            <a href="{{ route('comptes.achat-sur-solde', $item['compte']) }}" wire:navigate
                               class="text-xs text-emerald-700 border border-emerald-300 rounded-lg px-3 py-1 hover:bg-emerald-50">
                                Acheter avec le solde
                            </a>
                        @endif
                        <a href="{{ route('comptes.complement', $item['compte']) }}" wire:navigate
                           class="text-xs text-blue-700 border border-blue-300 rounded-lg px-3 py-1 hover:bg-blue-50">
                            Complément
                        </a>
                        @if ($item['compte']->politique()?->versement_dividendes_possible)
                            <a href="{{ route('comptes.paiement', $item['compte']) }}" wire:navigate
                               class="text-xs text-teal-700 border border-teal-300 rounded-lg px-3 py-1 hover:bg-teal-50">
                                Paiement
                            </a>
                        @endif
                        <a href="{{ route('dons.creer', $item['compte']) }}" wire:navigate
                           class="text-xs text-indigo-700 border border-indigo-300 rounded-lg px-3 py-1 hover:bg-indigo-50">
                            Don
                        </a>
                        @if ($item['compte']->politique()?->radiation_autorisee)
                            <a href="{{ route('radiations.creer', $item['compte']) }}" wire:navigate
                               class="text-xs text-red-700 border border-red-300 rounded-lg px-3 py-1 hover:bg-red-50">
                                Radiation
                            </a>
                        @endif
                        @if (in_array(auth()->user()->role, ['direction', 'administrateur']))
                            <a href="{{ route('comptes.ajustement', $item['compte']) }}" wire:navigate
                               class="text-xs text-amber-700 border border-amber-300 rounded-lg px-3 py-1 hover:bg-amber-50">
                                Ajustement
                            </a>
                        @endif
                    @endif
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-6 mb-4">
                <div>
                    <div class="text-xs text-gray-500 uppercase">Actions détenues</div>
                    <div class="text-2xl font-semibold text-gray-800">{{ number_format($item['nombre_actions'], 0, ',', ' ') }}</div>
                </div>
                <div>
                    <div class="text-xs text-gray-500 uppercase">Solde du compte financier</div>
                    <div class="text-2xl font-semibold text-emerald-700">{{ number_format($item['solde'], 0, ',', ' ') }} CFA</div>
                </div>
            </div>

            @if ($item['achats']->isNotEmpty())
                <div class="mb-4">
                    <div class="text-xs text-gray-500 uppercase mb-2">Historique des achats</div>
                    <livewire:achats.achats-historique :compte="$item['compte']" :key="'achats-'.$item['compte']->id" />
                </div>
            @endif

            @if ($item['compte']->radiations()->exists())
                <div class="mb-4">
                    <div class="text-xs text-gray-500 uppercase mb-2">Historique des radiations</div>
                    <livewire:radiations.radiations-historique :compte="$item['compte']" :key="'radiations-'.$item['compte']->id" />
                </div>
            @endif

            @if ($item['dernieres_ecritures']->isNotEmpty())
                <div class="mt-4">
                    <div class="text-xs text-gray-500 uppercase mb-2">Écritures du compte financier</div>
                    <livewire:comptes.ecritures-historique :compte="$item['compte']" :key="'ecritures-'.$item['compte']->id" />
                </div>
            @else
                <p class="text-sm text-gray-400 italic">Aucune écriture pour l'instant sur ce compte.</p>
            @endif
        </div>
    @empty
        <div class="bg-white border rounded-lg p-8 text-center text-gray-400">
            Aucun compte pour l'instant. Un compte sera créé automatiquement au premier achat d'actions
            (Commercial ou Waqf selon le type d'achat).
        </div>
    @endforelse
</div>
