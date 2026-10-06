<div class="p-4 sm:p-6 lg:p-8">
    <a href="{{ route('investisseurs.index') }}" wire:navigate class="text-sm text-gray-500 hover:underline">{{ __("← Retour à la liste") }}</a>

    <div class="mt-2 mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div class="min-w-0">
            <x-surtitre>{{ __("Dossier investisseur") }}</x-surtitre>
            <h1 class="mt-1 text-2xl sm:text-3xl font-bold text-gray-900">
                {{ $investisseur->nom }} {{ $investisseur->prenom }}
            </h1>
            <div class="mt-1 flex flex-wrap items-center gap-2">
                <span class="font-mono text-sm text-gray-500">{{ $investisseur->identifiant_externe }}</span>
                <span class="rounded-full px-2.5 py-0.5 text-xs font-medium
                    {{ $investisseur->statut === 'actif' ? 'bg-primaire-100 text-primaire-800' : ($investisseur->statut === 'decede' ? 'bg-gray-800 text-white' : 'bg-gray-100 text-gray-600') }}">
                    {{ $investisseur->statut === 'decede' ? __("Décédé(e)") : __($investisseur->statut) }}
                </span>
            </div>
        </div>

        {{-- Les deux gestes du quotidien, à portée. Le reste — décès, désactivation —
             descend plus bas : ce sont des actes rares, qui ne doivent pas se trouver
             sous la main de qui vient simplement consulter un dossier. --}}
        @if (auth()->user()->role !== 'lecture' && ! $investisseur->estDecede())
            <div class="flex shrink-0 flex-wrap gap-2">
                <a href="{{ route('investisseurs.modifier', $investisseur) }}" wire:navigate
                   class="inline-flex items-center gap-2 rounded-champ border border-gray-300 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50">
                    {{ __("Modifier le dossier") }}
                </a>
                <a href="{{ route('achats.creer', $investisseur) }}" wire:navigate
                   class="inline-flex items-center gap-2 rounded-champ bg-primaire-700 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-primaire-800">
                    {{ __("+ Nouvel achat") }}
                </a>
            </div>
        @endif
    </div>

    @if ($investisseur->estDecede())
        <div class="bg-gray-800 text-white rounded-champ p-4 mb-6">
            <p class="text-sm font-semibold mb-1">
                ⚠️ {{ __("Investisseur décédé le :date — comptes gelés.", ["date" => $investisseur->date_deces?->format('d/m/Y')]) }}
            </p>
            <p class="text-xs text-gray-300 mb-3">
                {{ __("Plus aucun achat, complément, paiement, don ou radiation n'est possible sur ses comptes.") }}
                @if ($investisseur->succession_reglee)
                    {{ __("La succession a déjà été réglée.") }}
                @else
                    {{ __("Gérez la succession pour répartir les avoirs entre les héritiers.") }}
                @endif
            </p>
            <div class="flex gap-2 flex-wrap">
                @if ($investisseur->piece_acte_deces_path)
                    <a href="{{ \App\Support\Document::lien($investisseur, 'piece_acte_deces_path') }}" target="_blank" class="text-xs text-gray-300 border border-gray-600 rounded-champ px-3 py-1.5 hover:bg-gray-700">
                        {{ __("Voir l'acte de décès") }}
                    </a>
                @endif
                @if (in_array(auth()->user()->role, ['direction', 'administrateur']))
                    <a href="{{ route('successions.gerer', $investisseur) }}" wire:navigate class="text-xs text-white bg-primaire-700 rounded-champ px-3 py-1.5 hover:bg-primaire-800">
                        {{ $investisseur->succession_reglee ? __("Voir la succession") : __("Gérer la succession") }}
                    </a>
                @endif
            </div>
        </div>
    @endif

    @php($manquants = $investisseur->champsManquants())
    @php($actionsCommerciales = $comptesEnrichis->firstWhere('compte.categorie', 'commercial')['nombre_actions'] ?? 0)
    @php($actionsWaqf = $comptesEnrichis->firstWhere('compte.categorie', 'waqf')['nombre_actions'] ?? 0)
    @php($soldeTotal = $comptesEnrichis->sum('solde'))

    {{--
        Ce qu'il détient, avant qui il est. Un gestionnaire qui ouvre un dossier
        cherche d'abord la position — ces chiffres étaient jusqu'ici tout en bas de
        la page, sous l'état civil et les pièces jointes.
    --}}
    <div class="mb-6 overflow-hidden rounded-carte bg-nuit-900 shadow-sm">
        <div class="flex flex-col gap-6 p-6 lg:flex-row lg:items-center lg:gap-10">
            <div class="lg:w-56 lg:shrink-0">
                <x-surtitre class="text-white/40">{{ __("Position") }}</x-surtitre>
                <p class="mt-1 text-lg font-bold text-white">{{ __("Ce qu'il détient") }}</p>
                <p class="mt-1 text-sm text-white/60">
                    {{ __(":nombre compte(s)", ['nombre' => $comptesEnrichis->count()]) }}
                </p>
            </div>

            <div class="grid flex-1 grid-cols-1 gap-5 sm:grid-cols-3 lg:gap-6 lg:divide-x lg:divide-white/10 rtl:lg:divide-x-reverse">
                @foreach ([
                    ['etiquette' => __("Actions commerciales"), 'valeur' => \App\Support\Montant::format($actionsCommerciales), 'accent' => false],
                    ['etiquette' => __("Actions waqf"), 'valeur' => \App\Support\Montant::format($actionsWaqf), 'accent' => false],
                    ['etiquette' => __("Solde total"), 'valeur' => \App\Support\Montant::avecDevise($soldeTotal), 'accent' => true],
                ] as $i => $chiffre)
                    <div class="{{ $i > 0 ? 'lg:ps-6' : '' }}">
                        <p class="text-xs font-medium uppercase tracking-wide text-white/50">{{ $chiffre['etiquette'] }}</p>
                        <p class="mt-1 text-xl font-bold sm:text-2xl {{ $chiffre['accent'] ? 'text-primaire-400' : 'text-white' }}">
                            {{ $chiffre['valeur'] }}
                        </p>
                    </div>
                @endforeach
            </div>
        </div>

        @if ($manquants && auth()->user()->role !== 'lecture')
            <a href="{{ route('investisseurs.modifier', $investisseur) }}" wire:navigate
               class="flex items-center gap-4 border-t border-white/10 px-6 py-4 transition hover:bg-white/5">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-champ bg-or-500/15 text-or-300">
                    <x-icone nom="alerte" class="h-5 w-5" />
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block text-sm font-semibold text-white">
                        {{ __("Dossier incomplet — :nombre élément(s) manquant(s)", ['nombre' => count($manquants)]) }}
                    </span>
                    <span class="block truncate text-xs text-white/50">
                        {{ collect($manquants)->map(fn ($m) => __($m))->implode(' · ') }}
                    </span>
                </span>
                <svg class="h-5 w-5 shrink-0 text-white/40 rtl:-scale-x-100" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                </svg>
            </a>
        @endif
    </div>

    @if (session('erreur_acces'))
        <div class="bg-red-50 border border-red-200 rounded-champ p-3 mb-4 text-sm text-red-700">
            {{ session('erreur_acces') }}
        </div>
    @endif

    @if (session('succes'))
        <div class="bg-primaire-50 border border-primaire-200 text-primaire-700 rounded-champ p-3 mb-4 text-sm">
            {{ session('succes') }}
        </div>
    @endif

    @if ($dernierMotDePasseGenere)
        @php($lienWhatsapp = \App\Support\MessageWhatsapp::lienAcces($investisseur, $dernierMotDePasseGenere, $accesVientDEtreCree))
        <div class="bg-amber-50 border border-amber-200 rounded-champ p-4 mb-6 text-sm text-amber-800">
            @if ($investisseur->user?->email)
                ✓ {{ __("Identifiants de connexion envoyés par email à :email.", ["email" => $investisseur->user->email]) }}
                <span class="text-xs text-amber-600">({{ __("mot de passe généré") }} : <span class="font-mono">{{ $dernierMotDePasseGenere }}</span>, {{ __("au cas où l'email n'arrive pas") }})</span>
            @else
                ✓ {{ __("Accès créé — connexion par téléphone (:telephone). Pas d'email sur ce dossier, transmettez le mot de passe directement à l'investisseur :", ["telephone" => $investisseur->user?->telephone]) }}
                <span class="text-sm font-mono font-semibold">{{ $dernierMotDePasseGenere }}</span>
            @endif

            {{-- Le mot de passe n'est lisible que sur ce rendu-ci : le bouton doit
                 donc être là, maintenant. Une fois la page quittée, il ne reste que
                 la réinitialisation pour en produire un nouveau. --}}
            @if ($lienWhatsapp)
                <div class="mt-3">
                    <a href="{{ $lienWhatsapp }}" target="_blank" rel="noopener noreferrer"
                       class="inline-flex items-center gap-2 whitespace-nowrap text-sm text-white bg-primaire-600 rounded-champ px-4 py-2 hover:bg-primaire-700">
                        <svg width="16" height="16" class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                            <path d="M17.47 14.38c-.3-.15-1.77-.87-2.04-.97-.27-.1-.47-.15-.67.15-.2.3-.77.97-.94 1.17-.17.2-.35.22-.65.07-.3-.15-1.26-.46-2.4-1.48-.89-.79-1.49-1.77-1.66-2.07-.17-.3-.02-.46.13-.61.14-.13.3-.35.45-.52.15-.17.2-.3.3-.5.1-.2.05-.37-.02-.52-.08-.15-.67-1.62-.92-2.22-.24-.58-.49-.5-.67-.51h-.57c-.2 0-.52.07-.8.37-.27.3-1.04 1.02-1.04 2.48s1.07 2.88 1.22 3.08c.15.2 2.1 3.2 5.08 4.49.71.3 1.26.49 1.69.63.71.22 1.36.19 1.87.12.57-.09 1.77-.72 2.02-1.42.25-.7.25-1.29.17-1.42-.07-.13-.27-.2-.57-.35Z"/>
                            <path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2 22l5.25-1.38a9.87 9.87 0 0 0 4.79 1.22h.01c5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.84 9.84 0 0 0 12.04 2Zm0 18.13h-.01a8.2 8.2 0 0 1-4.18-1.15l-.3-.18-3.11.82.83-3.04-.2-.31a8.2 8.2 0 0 1-1.26-4.36c0-4.54 3.7-8.23 8.24-8.23 2.2 0 4.27.86 5.82 2.41a8.18 8.18 0 0 1 2.41 5.83c0 4.54-3.7 8.23-8.24 8.23Z"/>
                        </svg>
                        {{ __("Envoyer par WhatsApp") }}
                    </a>
                    <p class="mt-1 text-xs text-amber-600">
                        {{ __("WhatsApp s'ouvre avec le message déjà écrit pour :numero — il vous reste à l'envoyer.", ["numero" => \App\Support\MessageWhatsapp::numeroDestinataire($investisseur)]) }}
                    </p>
                </div>
            @endif
        </div>
    @endif

    @if (session('message_acces'))
        <div class="mb-4 text-sm text-blue-800 bg-blue-50 border border-blue-200 rounded-champ px-4 py-3">
            {{ session('message_acces') }}
        </div>
    @endif

    @php($identifiantConnexion = $investisseur->user?->email ?? $investisseur->user?->telephone)
    @php($peutAgirSurLAcces = auth()->user()->role !== 'lecture')

    {{-- Trois états, et non deux : un accès peut être révoqué sans être effacé.
         La révocation reste offerte sur le dossier d'un défunt — c'est là qu'elle
         sert le plus — alors que la création, la réinitialisation et le
         rétablissement n'y ont pas leur place. --}}
    <div class="mb-6 flex flex-wrap items-center gap-2">
        @if (! $investisseur->user_id)
            @if ($peutAgirSurLAcces && ! $investisseur->estDecede())
                <button wire:click="creerAcces" wire:confirm="{{ __("Créer un accès de connexion pour cet investisseur ?") }}"
                        class="text-sm text-blue-700 border border-blue-300 rounded-champ px-4 py-2 hover:bg-blue-50">
                    {{ __("Créer un accès au portail investisseur") }}
                </button>
            @endif
        @elseif ($investisseur->user->actif)
            <span class="inline-block text-sm text-primaire-700 bg-primaire-50 border border-primaire-200 rounded-champ px-4 py-2">
                ✓ {{ __("Accès portail activé (:identifiant)", ["identifiant" => $identifiantConnexion]) }}
            </span>

            @if ($peutAgirSurLAcces && ! $investisseur->estDecede())
                <button wire:click="reinitialiserMotDePasse" wire:confirm="{{ __("Générer un nouveau mot de passe temporaire pour :identifiant ?", ["identifiant" => $identifiantConnexion]) }}"
                        class="text-sm text-blue-600 border border-blue-300 rounded-champ px-4 py-2 hover:bg-blue-50">
                    {{ __("Réinitialiser le mot de passe") }}
                </button>
            @endif

            @if ($peutAgirSurLAcces)
                <button wire:click="revoquerAcces" wire:confirm="{{ __("Révoquer l'accès de :identifiant ? L'investisseur ne pourra plus se connecter et sa session en cours sera coupée. Le dossier, les parts et l'historique ne sont pas touchés, et l'accès pourra être rétabli.", ["identifiant" => $identifiantConnexion]) }}"
                        class="text-sm text-red-700 border border-red-300 rounded-champ px-4 py-2 hover:bg-red-50">
                    {{ __("Révoquer l'accès") }}
                </button>
            @endif
        @else
            <span class="inline-block text-sm text-gray-600 bg-gray-50 border border-gray-300 rounded-champ px-4 py-2">
                {{ __("Accès portail révoqué (:identifiant)", ["identifiant" => $identifiantConnexion]) }}
            </span>

            @if ($peutAgirSurLAcces && ! $investisseur->estDecede())
                <button wire:click="retablirAcces" wire:confirm="{{ __("Rétablir l'accès de :identifiant ? Le mot de passe reste celui qu'il avait avant la révocation.", ["identifiant" => $identifiantConnexion]) }}"
                        class="text-sm text-primaire-700 border border-primaire-300 rounded-champ px-4 py-2 hover:bg-primaire-50">
                    {{ __("Rétablir l'accès") }}
                </button>
            @endif
        @endif
    </div>

    {{-- Le dossier incomplet est annoncé dans le bandeau, en haut : signalé une fois,
         là où on regarde, plutôt que deux fois au milieu de la page. Un lecteur, qui
         ne peut rien compléter, voit tout de même la liste ici. --}}
    @if ($manquants && auth()->user()->role === 'lecture')
        <div class="bg-or-50 border border-or-300 rounded-carte p-4 mb-4">
            <p class="text-sm font-semibold text-or-700">
                {{ __("Dossier incomplet — :nombre élément(s) manquant(s)", ['nombre' => count($manquants)]) }}
            </p>
            <ul class="mt-2 text-sm text-or-700 list-disc list-inside space-y-0.5">
                @foreach ($manquants as $manquant)
                    <li>{{ __($manquant) }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 sm:gap-6 mb-4">
        <div class="bg-white border rounded-carte p-4">
            <div class="text-xs text-gray-500 uppercase">{{ __("Téléphone") }}</div>
            <div class="mt-1">{{ $investisseur->telephone ?: '—' }}</div>
            <x-lien-whatsapp :numero="$investisseur->whatsapp" :telephone="$investisseur->telephone" class="mt-1 text-sm" />
        </div>
        <div class="bg-white border rounded-carte p-4">
            <div class="text-xs text-gray-500 uppercase">{{ __("Email") }}</div>
            <div class="mt-1">{{ $investisseur->email ?: '—' }}</div>
        </div>
        <div class="bg-white border rounded-carte p-4">
            <div class="text-xs text-gray-500 uppercase">{{ __("Gestionnaire") }}</div>
            <div class="mt-1 flex items-center justify-between gap-2">
                <span>{{ $investisseur->gestionnaire?->user?->nom ?? '— ' . __('Non assigné') }}</span>
                @if (in_array(auth()->user()->role, ['direction', 'administrateur']) && ! $investisseur->estDecede())
                    <button wire:click="$toggle('afficherFormulaireTransfert')" class="text-xs text-primaire-700 hover:underline whitespace-nowrap">
                        {{ __("Changer →") }}
                    </button>
                @endif
            </div>
        </div>
        <div class="bg-white border rounded-carte p-4">
            <div class="text-xs text-gray-500 uppercase">{{ __("Localisation") }}</div>
            <div class="mt-1">{{ collect([$investisseur->ville, $investisseur->pays])->filter()->implode(', ') ?: '—' }}</div>
        </div>
        <div class="bg-white border rounded-carte p-4">
            <div class="text-xs text-gray-500 uppercase">{{ __("Date de naissance") }}</div>
            <div class="mt-1">{{ $investisseur->date_naissance?->format('d/m/Y') ?? '—' }}</div>
        </div>
        <div class="bg-white border rounded-carte p-4">
            <div class="text-xs text-gray-500 uppercase">{{ __("Lieu de naissance") }}</div>
            <div class="mt-1">{{ $investisseur->lieu_naissance ?: '—' }}</div>
        </div>
        <div class="bg-white border rounded-carte p-4">
            <div class="text-xs text-gray-500 uppercase">{{ __("Langue") }}</div>
            {{-- Dans la langue elle-même : « Français », « English », « العربية »,
                 comme dans le sélecteur de la barre supérieure. --}}
            <div class="mt-1">{{ \App\Support\Langue::DISPONIBLES[\App\Support\Langue::normaliser($investisseur->langue)]['libelle'] }}</div>
        </div>
    </div>

    @if ($afficherFormulaireTransfert)
        <div class="bg-white border rounded-carte p-4 mb-4">
            <p class="text-sm font-medium text-gray-700 mb-2">{{ __("Réassigner à un autre gestionnaire") }}</p>
            <form wire:submit="transfererGestionnaire" class="flex flex-col sm:flex-row gap-2 sm:items-end">
                <div class="flex-1">
                    <label class="text-xs text-gray-500">{{ __("Nouveau gestionnaire") }}</label>
                    <select wire:model="nouveauGestionnaireId" class="w-full border rounded px-3 py-2 text-sm">
                        <option value="">{{ __("— Choisir —") }}</option>
                        @foreach ($gestionnaires as $g)
                            <option value="{{ $g->id }}">{{ $g->nomComplet() }}</option>
                        @endforeach
                    </select>
                    @error('nouveauGestionnaireId') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                </div>
                <div class="flex-1">
                    <label class="text-xs text-gray-500">{{ __("Motif (optionnel)") }}</label>
                    <input type="text" wire:model="motifTransfert" class="w-full border rounded px-3 py-2 text-sm">
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="bg-primaire-700 text-white px-4 py-2 rounded-champ text-sm whitespace-nowrap">{{ __("Transférer") }}</button>
                    <button type="button" wire:click="$set('afficherFormulaireTransfert', false)" class="text-sm text-gray-500 hover:underline">{{ __("Annuler") }}</button>
                </div>
            </form>

            @if ($historiqueAffectations->isNotEmpty())
                <div class="mt-4 pt-4 border-t">
                    <p class="text-xs text-gray-500 uppercase mb-2">{{ __("Historique des affectations") }}</p>
                    <ul class="text-sm text-gray-600 space-y-1">
                        @foreach ($historiqueAffectations as $h)
                            <li>
                                {{ $h->date_transfert->format('d/m/Y') }} —
                                {{ $h->ancienGestionnaire?->user?->nom ?? __("Non assigné") }} → {{ $h->nouveauGestionnaire->user->nom }}
                                @if ($h->motif) <span class="text-gray-400">(<bdi>{{ $h->motif }}</bdi>)</span> @endif
                                @if ($h->effectuePar) <span class="text-gray-400">— {{ __("par") }} {{ $h->effectuePar->nom }}</span> @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    @endif

    @if ($investisseur->type_identification || $investisseur->numero_identification || $investisseur->piece_identite_path)
        <div class="bg-white border rounded-carte p-4 mb-4">
            <div class="text-xs text-gray-500 uppercase mb-2">{{ __("Pièce d'identité") }}</div>
            <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 text-sm">
                <div><span class="text-gray-500">{{ __("Type :") }}</span> {{ $investisseur->type_identification ?: '—' }}</div>
                <div><span class="text-gray-500">{{ __("Numéro :") }}</span> {{ $investisseur->numero_identification ?: '—' }}</div>
                <div><span class="text-gray-500">{{ __("Délivrée le :") }}</span> {{ $investisseur->date_delivrance_piece?->format('d/m/Y') ?? '—' }}</div>
                <div><span class="text-gray-500">{{ __("Lieu :") }}</span> {{ $investisseur->lieu_delivrance_piece ?: '—' }}</div>
                <div><span class="text-gray-500">{{ __("Expire le :") }}</span> {{ $investisseur->date_expiration_piece?->format('d/m/Y') ?? '—' }}</div>
            </div>
            @if ($investisseur->piece_identite_path)
                <a href="{{ \App\Support\Document::lien($investisseur, 'piece_identite_path') }}" target="_blank" class="inline-block mt-2 text-sm text-primaire-700 hover:underline">
                    {{ __("Voir le scan du document →") }}
                </a>
            @endif
        </div>
    @endif

    @if ($investisseur->convention_engagement_path)
        <div class="bg-white border rounded-carte p-4 mb-4">
            <div class="text-xs text-gray-500 uppercase mb-2">{{ __("Convention d'engagement") }}</div>
            <div class="text-sm">
                {{ __("Signée le :date", ["date" => $investisseur->date_signature_convention?->format('d/m/Y') ?? '—']) }}
                <a href="{{ \App\Support\Document::lien($investisseur, 'convention_engagement_path') }}" target="_blank" class="ms-2 text-primaire-700 hover:underline">
                    {{ __("Voir le document →") }}
                </a>
            </div>
        </div>
    @endif

    @if ($investisseur->type_personne === 'morale' && $investisseur->raison_sociale)
        <div class="bg-white border rounded-carte p-4 mb-4">
            <div class="text-xs text-gray-500 uppercase mb-2">{{ __("Entreprise") }}</div>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-sm">
                <div><span class="text-gray-500">{{ __("Raison sociale :") }}</span> {{ $investisseur->raison_sociale }}</div>
                <div><span class="text-gray-500">{{ __("RCCM :") }}</span> {{ $investisseur->rccm ?: '—' }}</div>
                <div><span class="text-gray-500">{{ __("NINEA :") }}</span> {{ $investisseur->ninea ?: '—' }}</div>
                <div><span class="text-gray-500">{{ __("Représentant :") }}</span> {{ $investisseur->representant_legal_nom ?: '—' }}</div>
            </div>
        </div>
    @endif

    @if ($investisseur->beneficiaire_nom)
        <div class="bg-white border rounded-carte p-4 mb-8">
            <div class="text-xs text-gray-500 uppercase mb-2">{{ __("Bénéficiaire désigné") }}</div>
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
        <div class="bg-amber-50 border border-amber-200 rounded-champ p-4 mb-8">
            <div class="text-xs text-amber-700 uppercase mb-1">{{ __("Notes internes") }}</div>
            <div class="text-sm text-amber-900 whitespace-pre-line"><bdi>{{ $investisseur->notes_internes }}</bdi></div>
        </div>
    @endif

    @if ($presents->isNotEmpty())
        <div class="bg-purple-50 border border-purple-200 rounded-champ p-4 mb-4">
            <h2 class="text-sm font-semibold text-purple-900 mb-1">{{ __("Présents Waqf offerts par cet investisseur") }}</h2>
            <p class="text-xs text-purple-700 mb-3">
                {{ __("Payées par cet investisseur, versées au Waqf caritatif « :waqf » — elles ne figurent donc pas dans ses comptes ci-dessous.", ["waqf" => \App\Models\Investisseur::NOM_WAQF_CARITATIF]) }}
            </p>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-xs text-purple-800 border-b border-purple-200">
                        <tr>
                            <th class="text-start py-1">{{ __("Date") }}</th>
                            <th class="text-start py-1">{{ __("Bénéficiaire") }}</th>
                            <th class="text-end py-1">{{ __("Actions") }}</th>
                            <th class="text-end py-1">{{ __("Montant") }}</th>
                            <th class="text-end py-1">{{ __("Attestation") }}</th>
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
                                <td class="py-1.5 text-end">{{ \App\Support\Montant::format($present->nombre_actions) }}</td>
                                <td class="py-1.5 text-end">{{ \App\Support\Montant::format($present->montant) }}&#8239;CFA</td>
                                <td class="py-1.5 text-end">
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
        <div class="bg-purple-50 border border-purple-200 rounded-champ p-4 mb-4">
            <h2 class="text-sm font-semibold text-purple-900 mb-1">{{ __("Présents Waqf reçus en son honneur") }}</h2>
            <p class="text-xs text-purple-700 mb-3">{{ __("Versées au Waqf caritatif en son nom : elles ne lui confèrent aucun droit patrimonial.") }}</p>
            <ul class="text-sm space-y-1">
                @foreach ($presentsRecus as $present)
                    <li class="text-gray-800">
                        {{ $present->date_achat->format('d/m/Y') }} —
                        {{ __(":nombre action(s)", ["nombre" => \App\Support\Montant::format($present->nombre_actions)]) }}
                        {{ __("offerte(s) par") }} <strong>{{ $present->offertPar?->nom }} {{ $present->offertPar?->prenom }}</strong>@if ($present->lien_avec_donateur) <span class="text-gray-500">({{ mb_strtolower($present->lien_avec_donateur) }})</span>@endif
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3 mb-3">
        {{-- Le bouton d'achat vit en haut de page, avec les autres gestes du quotidien :
             le répéter ici ferait deux fois le même bouton sur un seul écran. --}}
        <h2 class="text-lg font-bold text-gray-900">{{ __("Comptes d'investissement") }}</h2>
    </div>

    @forelse ($comptesEnrichis as $item)
        <div class="bg-white border rounded-carte p-5 mb-4">
            <div class="flex justify-between items-center mb-4">
                <div class="flex items-center gap-3">
                    <span class="px-2 py-1 text-xs font-semibold rounded {{ $item['compte']->categorie === 'commercial' ? 'bg-blue-100 text-blue-700' : 'bg-purple-100 text-purple-700' }}">
                        {{ __(\App\Support\Libelles::categorie($item['compte']->categorie)) }}
                    </span>
                    <span class="font-mono text-sm text-gray-500">{{ $item['compte']->numero_compte }}</span>
                </div>
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="text-xs px-2 py-1 rounded-full {{ $item['compte']->reinvestissement_auto ? 'bg-primaire-100 text-primaire-700' : 'bg-gray-100 text-gray-600' }}">
                        {{ __("Réinvestissement auto") }} : {{ $item['compte']->reinvestissement_auto ? __("Oui") : __("Non") }}
                    </span>
                    @if (auth()->user()->role !== 'lecture' && ! $investisseur->estDecede())
                        @if (! $item['compte']->reinvestissement_auto)
                            <a href="{{ route('comptes.achat-sur-solde', $item['compte']) }}" wire:navigate
                               class="text-xs text-primaire-700 border border-primaire-300 rounded-champ px-3 py-1 hover:bg-primaire-50">
                                {{ __("Acheter avec le solde") }}
                            </a>
                        @endif
                        <a href="{{ route('comptes.complement', $item['compte']) }}" wire:navigate
                           class="text-xs text-blue-700 border border-blue-300 rounded-champ px-3 py-1 hover:bg-blue-50">
                            {{ __("Complément") }}
                        </a>
                        @if ($item['compte']->politique()?->versement_dividendes_possible)
                            <a href="{{ route('comptes.paiement', $item['compte']) }}" wire:navigate
                               class="text-xs text-primaire-700 border border-primaire-300 rounded-champ px-3 py-1 hover:bg-primaire-50">
                                {{ __("Paiement") }}
                            </a>
                        @endif
                        {{-- Le bouton disparaît quand la catégorie interdit la cession :
                             mieux vaut ne pas le proposer que de refuser après coup. --}}
                        @if ($item['compte']->politique()?->cession_autorisee ?? true)
                            <a href="{{ route('dons.creer', $item['compte']) }}" wire:navigate
                               class="text-xs text-primaire-700 border border-primaire-300 rounded-champ px-3 py-1 hover:bg-primaire-50">
                                {{ __("Don") }}
                            </a>
                        @endif
                        @if ($item['compte']->politique()?->radiation_autorisee)
                            <a href="{{ route('radiations.creer', $item['compte']) }}" wire:navigate
                               class="text-xs text-red-700 border border-red-300 rounded-champ px-3 py-1 hover:bg-red-50">
                                {{ __("Radiation") }}
                            </a>
                        @endif
                        @if (in_array(auth()->user()->role, ['direction', 'administrateur']))
                            <a href="{{ route('comptes.ajustement', $item['compte']) }}" wire:navigate
                               class="text-xs text-amber-700 border border-amber-300 rounded-champ px-3 py-1 hover:bg-amber-50">
                                {{ __("Ajustement") }}
                            </a>
                        @endif
                    @endif
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-6 mb-4">
                <div>
                    <div class="text-xs text-gray-500 uppercase">{{ __("Actions détenues") }}</div>
                    <div class="text-2xl font-semibold text-gray-800">{{ \App\Support\Montant::format($item['nombre_actions']) }}</div>
                </div>
                <div>
                    <div class="text-xs text-gray-500 uppercase">{{ __("Solde du compte financier") }}</div>
                    <div class="text-2xl font-semibold text-primaire-700">{{ \App\Support\Montant::format($item['solde']) }}&#8239;CFA</div>
                </div>
            </div>

            @if ($item['achats']->isNotEmpty())
                <x-bloc-repliable :titre="__('Historique des achats')" cle="achats"
                                  :nombre="$item['achats']->count()" :replie="$blocsReplies['achats'] ?? false">
                    <livewire:achats.achats-historique :compte="$item['compte']" :key="'achats-'.$item['compte']->id" />
                </x-bloc-repliable>
            @endif

            @php($nbRadiations = $item['compte']->radiations()->count())
            @if ($nbRadiations > 0)
                <x-bloc-repliable :titre="__('Historique des radiations')" cle="radiations"
                                  :nombre="$nbRadiations" :replie="$blocsReplies['radiations'] ?? false">
                    <livewire:radiations.radiations-historique :compte="$item['compte']" :key="'radiations-'.$item['compte']->id" />
                </x-bloc-repliable>
            @endif

            @php($nbDons = $item['compte']->donsEmis()->count() + $item['compte']->donsRecus()->count())
            @if ($nbDons > 0)
                <x-bloc-repliable :titre="__('Historique des dons')" cle="dons"
                                  :nombre="$nbDons" :replie="$blocsReplies['dons'] ?? false">
                    <livewire:dons.dons-historique :compte="$item['compte']" :key="'dons-'.$item['compte']->id" />
                </x-bloc-repliable>
            @endif

            @if ($item['dernieres_ecritures']->isNotEmpty())
                <x-bloc-repliable :titre="__('Écritures du compte financier')" cle="ecritures"
                                  :nombre="$item['compte']->ecritures->count()" :replie="$blocsReplies['ecritures'] ?? false">
                    <livewire:comptes.ecritures-historique :compte="$item['compte']" :key="'ecritures-'.$item['compte']->id" />
                </x-bloc-repliable>
            @else
                <p class="text-sm text-gray-400 italic">{{ __("Aucune écriture pour l'instant sur ce compte.") }}</p>
            @endif
        </div>
    @empty
        <div class="bg-white border rounded-carte p-8 text-center text-gray-400">
            {{ __("Aucun compte pour l'instant. Un compte sera créé automatiquement au premier achat d'actions (Commercial ou Waqf selon le type d'achat).") }}
        </div>
    @endforelse

    {{--
        Le bas de page : emporter un document, puis les actes rares.

        Le relevé occupait le haut de la fiche, avant même l'identité. C'est une
        sortie — on le prend en partant — et la déclaration de décès comme la
        désactivation n'ont rien à faire sous la main de qui vient consulter.
    --}}
    <div class="mt-8 border-t border-gray-200 pt-6">
        <div class="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <x-surtitre>{{ __("Relevé de compte") }}</x-surtitre>
                <form action="{{ route('investisseurs.releve', $investisseur) }}" method="GET" target="_blank"
                      class="mt-2 flex flex-wrap items-end gap-2">
                    <div>
                        <label class="mb-1 block text-xs text-gray-500">{{ __("Du (optionnel)") }}</label>
                        <input type="date" name="date_debut" class="rounded-champ border-gray-300 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs text-gray-500">{{ __("Au (optionnel)") }}</label>
                        <input type="date" name="date_fin" class="rounded-champ border-gray-300 px-3 py-2 text-sm">
                    </div>
                    <button type="submit"
                            class="whitespace-nowrap rounded-champ border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-50">
                        {{ __("📄 Relevé de compte (PDF)") }}
                    </button>
                </form>
                <p class="mt-1 text-xs text-gray-400">{{ __("Laissez vide pour l'historique complet.") }}</p>
            </div>

            @if (auth()->user()->role !== 'lecture' && ! $investisseur->estDecede() && in_array(auth()->user()->role, ['direction', 'administrateur']))
                <div class="lg:text-end">
                    <x-surtitre>{{ __("Actes rares") }}</x-surtitre>
                    <div class="mt-2 flex flex-wrap gap-2 lg:justify-end">
                        <a href="{{ route('deces.declarer', $investisseur) }}" wire:navigate
                           class="rounded-champ border border-gray-300 bg-white px-4 py-2 text-sm text-gray-700 transition hover:bg-gray-50">
                            {{ __("Déclarer un décès") }}
                        </a>
                        <button wire:click="basculerActifInvestisseur" wire:confirm="{{ $investisseur->statut === 'actif' ? __("Désactiver ce dossier ? L'accès portail sera coupé s'il en a un.") : __("Réactiver ce dossier ?") }}"
                                class="rounded-champ border border-gray-300 bg-white px-4 py-2 text-sm text-gray-700 transition hover:bg-gray-50">
                            {{ $investisseur->statut === 'actif' ? __("Désactiver") : __("Réactiver") }}
                        </button>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
