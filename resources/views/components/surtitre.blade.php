{{--
    Petite capitale posée au-dessus d'un titre de page, pour la situer.

    Elle reprend le nom de la section de la barre latérale. Sur grand écran c'est
    un rappel ; sur téléphone, où la barre n'existe pas — seulement les onglets du
    bas —, c'est la seule chose qui dise où l'on se trouve.

    Les pages d'action n'en portent pas : elles ont déjà un lien de retour au-dessus
    et le nom du dossier en dessous. Un surtitre de plus n'y ajouterait rien.
--}}
<p {{ $attributes->merge(['class' => 'text-xs font-semibold uppercase tracking-wider text-gray-400']) }}>
    {{ $slot }}
</p>
