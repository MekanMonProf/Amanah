<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 30px 40px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1f2937; }
        .en-tete { border-bottom: 2px solid #047857; padding-bottom: 10px; margin-bottom: 24px; }
        .en-tete .nom-projet { font-size: 18px; font-weight: bold; color: #047857; }
        .en-tete .sous-titre { font-size: 11px; color: #6b7280; }
        h1 { font-size: 16px; text-align: center; margin: 20px 0 6px 0; text-transform: uppercase; letter-spacing: 1px; }
        .numero { text-align: center; color: #6b7280; font-size: 11px; margin-bottom: 26px; }
        .texte { line-height: 1.8; text-align: justify; margin-bottom: 20px; }
        table.details { width: 100%; border-collapse: collapse; margin: 20px 0; }
        table.details td { padding: 7px 10px; border: 1px solid #e5e7eb; }
        table.details td.label { background-color: #f9fafb; color: #6b7280; width: 220px; }
        .montant { font-size: 15px; font-weight: bold; color: #047857; }
        .montant-sortant { color: #b91c1c; }
        .pied-page { margin-top: 60px; padding-top: 10px; border-top: 1px solid #e5e7eb; font-size: 9px; color: #9ca3af; }
        .zone-signature { margin-top: 44px; }
    </style>
</head>
<body>
    <div class="en-tete">
        @include('pdf.partials.en-tete')
    </div>

    @php
        $montant = abs((float) $ecriture->montant);
        $entree = (float) $ecriture->montant >= 0;
    @endphp

    <h1>{{ $entree ? "Reçu d'encaissement" : "Reçu de versement" }}</h1>
    <div class="numero">N° {{ $numero }}</div>

    <p class="texte">
        AND DOX S.A. atteste que
        @if ($entree)
            la somme de <strong>{{ number_format($montant, 0, ',', ' ') }} CFA</strong> a été portée au compte
        @else
            la somme de <strong>{{ number_format($montant, 0, ',', ' ') }} CFA</strong> a été versée depuis le compte
        @endif
        <strong>{{ $compte->numero_compte }}</strong>
        de <strong>{{ $investisseur->nom }} {{ $investisseur->prenom }}</strong>
        (identifiant {{ $investisseur->identifiant_externe }}),
        le {{ $ecriture->date_ecriture->translatedFormat('d F Y') }},
        au titre de : {{ \App\Support\Libelles::typeEcriture($ecriture->type_ecriture) }}.
    </p>

    <table class="details">
        <tr>
            <td class="label">N° de reçu</td>
            <td>{{ $numero }}</td>
        </tr>
        <tr>
            <td class="label">Compte</td>
            <td>{{ $compte->numero_compte }} ({{ \App\Support\Libelles::categorie($compte->categorie) }})</td>
        </tr>
        <tr>
            <td class="label">Nature de l'opération</td>
            <td>{{ \App\Support\Libelles::typeEcriture($ecriture->type_ecriture) }}</td>
        </tr>
        <tr>
            <td class="label">Date</td>
            <td>{{ $ecriture->date_ecriture->format('d/m/Y') }}</td>
        </tr>
        <tr>
            <td class="label">{{ $entree ? 'Montant encaissé' : 'Montant versé' }}</td>
            <td class="montant {{ $entree ? '' : 'montant-sortant' }}">
                {{ $entree ? '+' : '−' }}{{ number_format($montant, 0, ',', ' ') }} CFA
            </td>
        </tr>
        <tr>
            <td class="label">Solde du compte après opération</td>
            <td>{{ number_format((float) $ecriture->solde_apres, 0, ',', ' ') }} CFA</td>
        </tr>
        @if ($ecriture->observation_affichee)
        <tr>
            <td class="label">Observation</td>
            <td>{{ $ecriture->observation_affichee }}</td>
        </tr>
        @endif
    </table>

    <div class="zone-signature">
        <p>Fait à Dakar, le {{ $dateGeneration->translatedFormat('d F Y') }}.</p>
        <br><br>
        <p>Pour AND DOX S.A.</p>
    </div>

    <div class="pied-page">
        Document généré automatiquement par la plateforme AMANAH le {{ $dateGeneration->format('d/m/Y à H:i') }}.
        Ce reçu se rapporte à une seule opération ; le relevé de compte en donne l'historique complet.
    </div>
</body>
</html>
