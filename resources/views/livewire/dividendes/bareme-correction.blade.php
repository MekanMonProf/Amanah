<div class="p-4 sm:p-6 max-w-2xl">
    <a href="{{ route('dividendes.calculer') }}" wire:navigate class="text-sm text-gray-500 hover:underline">← Retour au calcul des dividendes</a>

    <h1 class="text-xl sm:text-2xl font-semibold text-gray-800 mt-2 mb-1">Corriger un barème</h1>
    <p class="text-sm text-gray-500 mb-6">
        {{ $bareme->periode->translatedFormat('F Y') }} · {{ ucfirst($bareme->categorie) }}
    </p>

    <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6 text-sm text-red-800">
        ⚠️ <strong>Cette correction affecte {{ $nbComptesConcernes }} compte(s)</strong> ayant déjà reçu le
        dividende de cette période à l'ancien taux. Chacun recevra automatiquement une écriture d'ajustement
        correctrice — rien n'est supprimé, tout reste tracé. Si un compte avait déjà utilisé ce dividende pour
        un réinvestissement automatique, son solde peut devenir négatif si le nouveau taux est inférieur :
        c'est le reflet honnête de l'erreur, à régulariser au besoin avec l'investisseur.
    </div>

    @if ($resultat)
        <div class="bg-emerald-50 border border-emerald-200 rounded-lg p-4 mb-6 text-sm text-emerald-800">
            ✓ Correction appliquée : <strong>{{ $resultat['nb_comptes'] }}</strong> compte(s) ajusté(s),
            pour un total de <strong>{{ \App\Support\Montant::format($resultat['total_ajuste']) }}&#8239;CFA</strong>
            {{ $resultat['total_ajuste'] >= 0 ? 'crédités' : 'débités' }} au global.
        </div>
    @endif

    <form wire:submit="corriger" class="bg-white border rounded-lg p-5 shadow-sm space-y-4">
        <div>
            <div class="text-xs text-gray-500 uppercase">Taux actuel</div>
            <div class="text-xl font-semibold text-gray-800">{{ \App\Support\Montant::format($ancienTaux) }}&#8239;CFA/action</div>
        </div>

        <div>
            <label class="text-sm text-gray-600">Nouveau taux (CFA/action)</label>
            <input type="number" step="0.01" wire:model="nouveauTaux" class="w-full border rounded px-3 py-2">
            @error('nouveauTaux') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
        </div>

        <div>
            <label class="text-sm text-gray-600">Motif de la correction (obligatoire, pour l'audit)</label>
            <textarea wire:model="motif" rows="3" class="w-full border rounded px-3 py-2"
                      placeholder="Ex : Taux saisi à 99 999 CFA par erreur de frappe le 08/08/2026, correction au taux réellement décidé par la Direction."></textarea>
            @error('motif') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
        </div>

        <button type="submit" wire:loading.attr="disabled" wire:target="corriger"
                class="bg-red-600 text-white px-5 py-2 rounded-lg w-full sm:w-auto hover:bg-red-700 disabled:opacity-50">
            <span wire:loading.remove wire:target="corriger">Corriger le barème et ajuster les comptes</span>
            <span wire:loading wire:target="corriger">Correction en cours...</span>
        </button>
    </form>
</div>
