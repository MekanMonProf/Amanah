{{--
    En-tête partagé par tous les documents PDF (attestations, listes, relevés) — logo +
    nom du projet. Le logo est intégré en base64 (pas de simple chemin ou d'URL) pour
    que DomPDF le rende de façon fiable quel que soit l'environnement, sans dépendre
    du support des images distantes.
--}}
@php
    // DomPDF a besoin de l'extension GD pour traiter un PNG avec canal alpha (absente sur
    // cet environnement) — on utilise ici une version aplatie sur fond blanc du même logo
    // (logo.png), visuellement identique sur une page PDF qui est de toute façon blanche.
    $cheminLogo = public_path('images/logo-pdf.jpg');
    $logoDataUri = file_exists($cheminLogo)
        ? 'data:image/jpeg;base64,' . base64_encode(file_get_contents($cheminLogo))
        : null;
@endphp
<table style="width: 100%; border-collapse: collapse;">
    <tr>
        @if ($logoDataUri)
            <td style="width: 44px; vertical-align: middle; padding: 0;">
                <img src="{{ $logoDataUri }}" style="height: 40px; width: auto;">
            </td>
        @endif
        <td style="vertical-align: middle; padding: 0 0 0 {{ $logoDataUri ? '10px' : '0' }};">
            <div class="nom-projet">AMANAH</div>
            <div class="sous-titre">Plateforme de Gestion des Investissements — AND DOX S.A.</div>
        </td>
    </tr>
</table>
