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
@endphp
<table style="width: 100%; border-collapse: collapse;">
    <tr>
        @if ($logoAndDox)
            <td style="width: 44px; vertical-align: middle; padding: 0;">
                <img src="{{ $logoAndDox }}" style="height: 40px; width: auto;">
            </td>
        @endif
        <td style="vertical-align: middle; padding: 0 0 0 {{ $logoAndDox ? '10px' : '0' }};">
            <div class="nom-projet">AMANAH</div>
            <div class="sous-titre">Plateforme de Gestion des Investissements — AND DOX S.A.</div>
        </td>
        @if ($logoWaqf)
            <td style="width: 50px; vertical-align: middle; padding: 0; text-align: right;">
                {{-- Un peu plus haut que le logo AND DOX : le Waqf est quasi carre, il
                     parait sinon nettement plus petit a hauteur egale. --}}
                <img src="{{ $logoWaqf }}" style="height: 54px; width: auto;">
            </td>
        @endif
    </tr>
</table>
