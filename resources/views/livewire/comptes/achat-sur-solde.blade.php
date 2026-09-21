<div class="p-4 sm:p-6 max-w-2xl">
    <a href="{{ route('investisseurs.show', $compte->investisseur) }}" wire:navigate class="text-sm text-gray-500 hover:underline">{{ __("← Retour au dossier") }}</a>

    <h1 class="text-xl sm:text-2xl font-semibold text-gray-800 mt-2 mb-1">{{ __("Achat manuel sur solde disponible") }}</h1>
    <p class="text-sm text-gray-500 mb-6">
        {{ $compte->investisseur->nom }} {{ $compte->investisseur->prenom }} ·
        <span class="font-mono">{{ $compte->numero_compte }}</span> ({{ ucfirst($compte->categorie) }})
    </p>

    @if (!$compte->reinvestissement_auto)
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6 text-sm text-blue-800">
            ℹ️ {!! __("Le réinvestissement automatique est <strong>désactivé</strong> sur ce compte — les dividendes s'accumulent sans jamais acheter d'actions tant que vous ne le décidez pas ici, manuellement.") !!}
        </div>
    @endif

    @if ($resultatNbAchats !== null)
        <div class="bg-emerald-50 border border-emerald-200 rounded-lg p-4 mb-6 text-sm text-emerald-800">
            @if ($resultatNbAchats > 0)
                ✓ {{ $resultatNbAchats }} action(s) achetée(s) avec succès à partir du solde disponible.
            @else
                Le solde actuel ne couvre pas le prix d'une action complète — rien à acheter pour l'instant.
            @endif
        </div>
    @endif

    <div class="bg-white border rounded-lg p-5 shadow-sm space-y-4">
        <div class="grid grid-cols-2 gap-4">
            <div>
                <div class="text-xs text-gray-500 uppercase">{{ __("Solde disponible") }}</div>
                <div class="text-xl font-semibold text-gray-800">{{ \App\Support\Montant::format($compte->solde()) }}&#8239;CFA</div>
            </div>
            <div>
                <div class="text-xs text-gray-500 uppercase">{{ __("Prix d'une action") }}</div>
                <div class="text-xl font-semibold text-gray-800">{{ \App\Support\Montant::format($prixAction) }}&#8239;CFA</div>
            </div>
        </div>

        @if ($nbActionsPossibles > 0)
            <div class="bg-emerald-50 border border-emerald-200 rounded-lg p-4">
                <p class="text-sm text-emerald-800 mb-1">
                    Ce solde permet d'acheter <strong>{{ $nbActionsPossibles }}</strong> action(s), pour un montant de
                    <strong>{{ \App\Support\Montant::format($montantUtilise) }}&#8239;CFA</strong>.
                </p>
                <p class="text-xs text-emerald-700">
                    Reliquat conservé sur le compte après achat : {{ \App\Support\Montant::format($reliquat) }}&#8239;CFA.
                </p>
            </div>

            <button wire:click="confirmerAchat" wire:loading.attr="disabled" wire:target="confirmerAchat"
                    class="bg-emerald-700 text-white px-5 py-2 rounded-lg w-full sm:w-auto disabled:opacity-50">
                <span wire:loading.remove wire:target="confirmerAchat">Confirmer l'achat de {{ $nbActionsPossibles }} action(s)</span>
                <span wire:loading wire:target="confirmerAchat">{{ __("Traitement...") }}</span>
            </button>
        @else
            <div class="bg-gray-50 border rounded-lg p-4 text-sm text-gray-500">
                Le solde actuel ({{ \App\Support\Montant::format($compte->solde()) }}&#8239;CFA) ne couvre pas le prix
                d'une action ({{ \App\Support\Montant::format($prixAction) }}&#8239;CFA). Rien à acheter pour l'instant.
            </div>
        @endif
    </div>
</div>
