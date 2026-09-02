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
        h1 { font-size: 16px; text-align: center; margin: 20px 0 30px 0; text-transform: uppercase; letter-spacing: 1px; }
        .texte { line-height: 1.8; text-align: justify; margin-bottom: 20px; }
        table.details { width: 100%; border-collapse: collapse; margin: 20px 0; }
        table.details td { padding: 7px 10px; border: 1px solid #e5e7eb; }
        table.details td.label { background-color: #f9fafb; color: #6b7280; width: 200px; }
        .montant-total { font-size: 15px; font-weight: bold; color: #047857; }
        .statut-paye { color: #047857; font-weight: bold; }
        .statut-partiel { color: #b45309; font-weight: bold; }
        .statut-non-paye { color: #6b7280; font-weight: bold; }
        .pied-page { margin-top: 60px; padding-top: 10px; border-top: 1px solid #e5e7eb; font-size: 9px; color: #9ca3af; }
        .zone-signature { margin-top: 50px; }
    </style>
</head>
<body>
    <div class="en-tete">
        @include('pdf.partials.en-tete')
    </div>

    <h1>Attestation de radiation d'actions</h1>

    <p class="texte">
        AND DOX S.A. atteste que <strong>{{ $investisseur->nom }} {{ $investisseur->prenom }}</strong>
        (identifiant {{ $investisseur->identifiant_externe }}) a procédé à la radiation de
        <strong>{{ number_format($radiation->nombre_actions_radiees, 0, ',', ' ') }} action(s)</strong>
        de catégorie <strong>{{ ucfirst($compte->categorie) }}</strong>, le
        {{ $radiation->date_radiation->translatedFormat('d F Y') }}, donnant droit à un capital de
        {{ number_format($radiation->montant_total, 0, ',', ' ') }} CFA.
    </p>

    <table class="details">
        <tr>
            <td class="label">N° de radiation</td>
            <td>{{ $radiation->numero_radiation }}</td>
        </tr>
        <tr>
            <td class="label">Compte</td>
            <td>{{ $compte->numero_compte }} ({{ ucfirst($compte->categorie) }})</td>
        </tr>
        <tr>
            <td class="label">Actions radiées</td>
            <td>{{ number_format($radiation->nombre_actions_radiees, 0, ',', ' ') }}</td>
        </tr>
        <tr>
            <td class="label">Prix unitaire</td>
            <td>{{ number_format($radiation->prix_unitaire_action, 0, ',', ' ') }} CFA</td>
        </tr>
        <tr>
            <td class="label">Montant total du capital</td>
            <td class="montant-total">{{ number_format($radiation->montant_total, 0, ',', ' ') }} CFA</td>
        </tr>
        <tr>
            <td class="label">Montant déjà versé</td>
            <td>{{ number_format($montantVerse, 0, ',', ' ') }} CFA</td>
        </tr>
        <tr>
            <td class="label">Statut du versement</td>
            <td class="{{ $statutPaiement === 'Payé' ? 'statut-paye' : ($statutPaiement === 'Partiellement payé' ? 'statut-partiel' : 'statut-non-paye') }}">
                {{ $statutPaiement }}
            </td>
        </tr>
        @if ($radiation->reference_facture)
        <tr>
            <td class="label">Référence</td>
            <td>{{ $radiation->reference_facture }}</td>
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
        Le statut de versement reflète l'état au moment de la génération de ce document.
    </div>
</body>
</html>
