{{--
    Bouton d'action principale. Hauteur 44 px et texte en casse normale, comme
    partout ailleurs : les petites capitales héritées du gabarit de départ ne
    ressemblaient à aucun autre bouton de l'application.
--}}
<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center justify-center gap-2 rounded-champ bg-primaire-700 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-primaire-800 focus:outline-none focus:ring-2 focus:ring-primaire-600 focus:ring-offset-2 active:bg-primaire-900 disabled:opacity-50']) }}>
    {{ $slot }}
</button>
