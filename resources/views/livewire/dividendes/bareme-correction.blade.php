<div class="p-4 sm:p-6 max-w-2xl">
    <a href="{{ route('dividendes.calculer') }}" wire:navigate class="text-sm text-gray-500 hover:underline">{{ __("← Retour au calcul des dividendes") }}</a>

    <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 mt-2 mb-1">{{ __("Corriger un barème") }}</h1>
    <p class="text-sm text-gray-500 mb-6">
        {{ $bareme->periode->translatedFormat('F Y') }} · {{ __(\App\Support\Libelles::categorie($bareme->categorie)) }}
    </p>

    <div class="bg-red-50 border border-red-200 rounded-champ p-4 mb-6 text-sm text-red-800">
        ⚠️ <strong>{{ __('Cette correction affecte :nombre compte(s)', ['nombre' => $nbComptesConcernes]) }}</strong>
        {{ __("ayant déjà reçu le dividende de cette période à l'ancien taux. Chacun recevra automatiquement une écriture d'ajustement correctrice — rien n'est supprimé, tout reste tracé. Si un compte avait déjà utilisé ce dividende pour un réinvestissement automatique, son solde peut devenir négatif si le nouveau taux est inférieur : c'est le reflet honnête de l'erreur, à régulariser au besoin avec l'investisseur.") }}
    </div>

    @if ($resultat)
        <div class="bg-primaire-50 border border-primaire-200 rounded-champ p-4 mb-6 text-sm text-primaire-800">
            ✓ {!! __("Correction appliquée : <strong>:nombre</strong> compte(s) ajusté(s), pour un total de <strong>:montant</strong> :sens au global.", ['nombre' => $resultat['nb_comptes'], 'montant' => \App\Support\Montant::avecDevise($resultat['total_ajuste']), 'sens' => $resultat['total_ajuste'] >= 0 ? __('crédités') : __('débités')]) !!}
        </div>

        @if (($resultat['nb_comptes_soldes'] ?? 0) > 0)
            <div class="bg-or-50 border border-or-300 rounded-champ p-4 mb-6 text-sm text-or-700">
                {!! __("<strong>:nombre</strong> compte(s) de succession déjà réglée n'ont pas été ajustés : le dossier est soldé et le mandataire payé. L'ajustement qui leur revient doit être traité à part.", ['nombre' => $resultat['nb_comptes_soldes']]) !!}
            </div>
        @endif
    @endif

    <form wire:submit="corriger" class="bg-white border rounded-carte p-5 space-y-4">
        <div>
            <div class="text-xs text-gray-500 uppercase">{{ __("Taux actuel") }}</div>
            <div class="text-xl font-semibold text-gray-800">{{ \App\Support\Montant::format($ancienTaux) }}&#8239;{{ __("CFA/action") }}</div>
        </div>

        <div>
            <label class="text-sm text-gray-600">{{ __("Nouveau taux (CFA/action)") }}</label>
            <input type="number" step="0.01" wire:model="nouveauTaux" class="w-full border rounded px-3 py-2">
            @error('nouveauTaux') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
        </div>

        <div>
            <label class="text-sm text-gray-600">{{ __("Motif de la correction (obligatoire, pour l'audit)") }}</label>
            <textarea wire:model="motif" rows="3" class="w-full border rounded px-3 py-2"
                      placeholder="{{ __('Ex : Taux saisi à 99 999 CFA par erreur de frappe le 08/08/2026, correction au taux réellement décidé par la Direction.') }}"></textarea>
            @error('motif') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
        </div>

        <button type="submit" wire:loading.attr="disabled" wire:target="corriger"
                class="bg-red-600 text-white px-5 py-2 rounded-champ w-full sm:w-auto hover:bg-red-700 disabled:opacity-50">
            <span wire:loading.remove wire:target="corriger">{{ __("Corriger le barème et ajuster les comptes") }}</span>
            <span wire:loading wire:target="corriger">{{ __("Correction en cours...") }}</span>
        </button>
    </form>
</div>
