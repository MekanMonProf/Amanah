@props(['disabled' => false])

{{-- Même rayon et même hauteur que les boutons : un champ et le bouton qui le suit
     doivent se répondre, sinon la ligne de formulaire boite. --}}
<input @disabled($disabled) {{ $attributes->merge(['class' => 'rounded-champ border-gray-300 bg-gray-50 py-2.5 text-sm transition focus:border-primaire-600 focus:bg-white focus:ring-primaire-600 disabled:opacity-60']) }}>
