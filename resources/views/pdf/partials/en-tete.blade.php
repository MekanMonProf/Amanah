{{--
    En-tête partagé par tous les documents PDF (attestations, listes, relevés) :
    logo AND DOX à gauche, identité du document au centre, logo Waqf Dolel Xamxam à droite.
    Les logos sont intégrés en base64 (pas de simple chemin ou d'URL) pour que DomPDF les
    rende de façon fiable quel que soit l'environnement, sans dépendre du support des
    images distantes.
--}}
@php
    // DomPDF a besoin de l'extension GD pour traiter un PNG avec canal alpha (absente sur
    // cet environnement) : on utilise donc, pour chaque logo, une version aplatie sur fond
    // blanc générée depuis le PNG d'origine (logo_anddox.png et logo_waqf.png). Le rendu
    // est identique, la page PDF étant de toute façon blanche.
    $enDataUri = function (string $fichier): ?string {
        $chemin = public_path('images/' . $fichier);

        return file_exists($chemin)
            ? 'data:image/jpeg;base64,' . base64_encode(file_get_contents($chemin))
            : null;
    };

    $logoAndDox = $enDataUri('logo-pdf.jpg');
    $logoWaqf = $enDataUri('logo-waqf-pdf.jpg');

    // Le nom et la ligne d'activite se reglent desormais dans Parametrage ->
    // La societe. Tant que personne ne les a remplis, le modele rend les
    // valeurs qui etaient ecrites ici : l'en-tete ne change pas de lui-meme.
    $societe = \App\Models\ParametreSociete::actuel();
    $sousTitre = collect([$societe->activite(), $societe->raisonSociale()])->filter()->implode(' — ');
@endphp
<table style="width: 100%; border-collapse: collapse;">
    <tr>
        @if ($logoAndDox)
            <td style="width: 68px; vertical-align: middle; padding: 0;">
                <img src="{{ $logoAndDox }}" style="height: 46px; width: auto;">
            </td>
        @endif
        <td style="vertical-align: middle; padding: 0 0 0 {{ $logoAndDox ? '10px' : '0' }};">
            {{-- Les capitales sont une affaire de typographie, pas de reglage :
                 l en-tete porte le nom en capitales depuis toujours, et il doit
                 continuer a le faire quel que soit le nom qu on y met. --}}
            <div class="nom-projet">{{ \Illuminate\Support\Str::upper($societe->nom()) }}</div>
            @if ($sousTitre !== '')
                <div class="sous-titre">{{ $sousTitre }}</div>
            @endif
        </td>
        @if ($logoWaqf)
            <td style="width: 50px; vertical-align: middle; padding: 0; text-align: right;">
                {{-- Plus haut que le logo AND DOX, qui est large la ou celui-ci est quasi
                     carre : c'est la surface, pas la hauteur, qui equilibre les deux. --}}
                <img src="{{ $logoWaqf }}" style="height: 54px; width: auto;">
            </td>
        @endif
    </tr>
</table>
