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
        h1 { font-size: 16px; text-align: center; margin: 20px 0 8px 0; text-transform: uppercase; letter-spacing: 1px; }
        .sous-h1 { text-align: center; font-size: 11px; color: #6b7280; margin-bottom: 24px; }
        .texte { line-height: 1.8; text-align: justify; margin-bottom: 20px; }
        table.details { width: 100%; border-collapse: collapse; margin: 10px 0 20px 0; }
        table.details td { padding: 7px 10px; border: 1px solid #e5e7eb; }
        table.details td.label { background-color: #f9fafb; color: #6b7280; width: 220px; }
        .section-titre { font-size: 12px; font-weight: bold; color: #374151; margin: 18px 0 6px 0; }
        .montant-total { font-size: 16px; font-weight: bold; color: #047857; }
        .pied-page { margin-top: 50px; padding-top: 10px; border-top: 1px solid #e5e7eb; font-size: 9px; color: #9ca3af; }
        .zone-signature { margin-top: 40px; }
    </style>
</head>
<body>
    <div class="en-tete">
        @include('pdf.partials.en-tete')
    </div>

    <h1>Attestation de versement — Succession</h1>
    <p class="sous-h1">Établie suite au décès de {{ $defunt->nom }} {{ $defunt->prenom }}</p>

    <p class="texte">
        AND DOX S.A. atteste avoir versé la somme de <strong>{{ number_format($montantVerse, 0, ',', ' ') }} CFA</strong>
        le {{ $ecriture->date_ecriture->translatedFormat('d F Y') }}, dans le cadre du règlement de la succession
        de <strong>{{ $defunt->nom }} {{ $defunt->prenom }}</strong>
        (identifiant {{ $defunt->identifiant_externe }}), décédé(e) le
        <strong>{{ $defunt->date_deces?->translatedFormat('d F Y') }}</strong>,
        au mandataire/procurataire désigné par la famille.
    </p>

    <div class="section-titre">Défunt</div>
    <table class="details">
        <tr>
            <td class="label">Nom</td>
            <td>{{ $defunt->nom }} {{ $defunt->prenom }}</td>
        </tr>
        <tr>
            <td class="label">Identifiant</td>
            <td>{{ $defunt->identifiant_externe }}</td>
        </tr>
        <tr>
            <td class="label">Date de décès</td>
            <td>{{ $defunt->date_deces?->format('d/m/Y') }}</td>
        </tr>
        <tr>
            <td class="label">Compte concerné</td>
            <td>{{ $compte->numero_compte }} ({{ ucfirst($compte->categorie) }})</td>
        </tr>
    </table>

    @if ($mandataire)
        <div class="section-titre">Mandataire / procurataire</div>
        <table class="details">
            <tr>
                <td class="label">Nom</td>
                <td>{{ $mandataire->nom }} {{ $mandataire->prenom }}</td>
            </tr>
            <tr>
                <td class="label">Lien avec le défunt</td>
                <td>{{ $mandataire->lien_parente }}</td>
            </tr>
            @if ($mandataire->telephone)
            <tr>
                <td class="label">Téléphone</td>
                <td>{{ $mandataire->telephone }}</td>
            </tr>
            @endif
        </table>
    @endif

    <div class="section-titre">Détail du versement</div>
    <table class="details">
        @if ($radiation)
        <tr>
            <td class="label">Actions liquidées</td>
            <td>{{ number_format($radiation->nombre_actions_radiees, 0, ',', ' ') }}</td>
        </tr>
        <tr>
            <td class="label">Prix unitaire de l'action</td>
            <td>{{ number_format($radiation->prix_unitaire_action, 0, ',', ' ') }} CFA</td>
        </tr>
        <tr>
            <td class="label">Montant des actions liquidées</td>
            <td>{{ number_format($montantActions, 0, ',', ' ') }} CFA</td>
        </tr>
        @endif
        <tr>
            <td class="label">Solde du compte (hors actions)</td>
            <td>{{ number_format($soldeHorsActions, 0, ',', ' ') }} CFA</td>
        </tr>
        <tr>
            <td class="label">Montant total perçu</td>
            <td class="montant-total">{{ number_format($montantVerse, 0, ',', ' ') }} CFA</td>
        </tr>
    </table>

    <div class="zone-signature">
        <p>Fait à Dakar, le {{ $dateGeneration->translatedFormat('d F Y') }}.</p>
        <br><br>
        <p>Pour AND DOX S.A.</p>
    </div>

    <div class="pied-page">
        Document généré automatiquement par la plateforme AMANAH le {{ $dateGeneration->format('d/m/Y à H:i') }}.
    </div>
</body>
</html>
