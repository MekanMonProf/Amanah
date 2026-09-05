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
        .pied-page { margin-top: 60px; padding-top: 10px; border-top: 1px solid #e5e7eb; font-size: 9px; color: #9ca3af; }
        .zone-signature { margin-top: 50px; }
    </style>
</head>
<body>
    <div class="en-tete">
        @include('pdf.partials.en-tete')
    </div>

    <h1>{{ $achat->estUnPresent() ? "Certificat d'Hommage et de Générosité" : 'Attestation d\'achat d\'actions' }}</h1>

    @if ($achat->estUnPresent())
        <p class="texte">
            AND DOX S.A. atteste que <strong>{{ $achat->offertPar->nom }} {{ $achat->offertPar->prenom }}</strong>
            (identifiant {{ $achat->offertPar->identifiant_externe }}) a offert
            <strong>{{ number_format($achat->nombre_actions, 0, ',', ' ') }} action(s)</strong> Waqf,
            au prix unitaire de {{ number_format($achat->prix_unitaire, 0, ',', ' ') }} CFA par action,
            le {{ $achat->date_achat->translatedFormat('d F Y') }},
            {{-- Expression plutot qu'un @if : Blade ne reconnait pas une directive collee a un
                 mot : sa regex exige un caractere non-mot juste avant l'arobase, sinon la
                 directive est recopiee telle quelle et la vue ne compile plus. --}}
            <strong>{{ $achat->formulePresent() }} {{ $achat->present_pour }}</strong>{{ $achat->lien_avec_donateur ? ', ' . mb_strtolower($achat->lien_avec_donateur) . ' du donateur' : '' }}.
        </p>
        <p class="texte">
            Conformément au principe d'inaliénabilité du Waqf, ces actions sont versées au compte
            institutionnel <strong>{{ $investisseur->nom }}</strong> ({{ $compte->numero_compte }}).
            Elles n'ouvrent aucun droit patrimonial — ni restitution du capital, ni versement de
            dividendes — au donateur, ni à la personne honorée.
        </p>
    @else
        <p class="texte">
            AND DOX S.A. atteste que <strong>{{ $investisseur->nom }} {{ $investisseur->prenom }}</strong>
            (identifiant {{ $investisseur->identifiant_externe }}) a acquis
            <strong>{{ number_format($achat->nombre_actions, 0, ',', ' ') }} action(s)</strong>
            de catégorie <strong>{{ ucfirst($compte->categorie) }}</strong>,
            au prix unitaire de {{ number_format($achat->prix_unitaire, 0, ',', ' ') }} CFA par action,
            le {{ $achat->date_achat->translatedFormat('d F Y') }}.
        </p>
    @endif

    <table class="details">
        @if ($achat->estUnPresent())
        <tr>
            <td class="label">{{ $achat->estEnMemoire() ? 'À la mémoire de' : 'Au profit de' }}</td>
            <td><strong>{{ $achat->present_pour }}</strong></td>
        </tr>
        @if ($achat->lien_avec_donateur)
        <tr>
            <td class="label">Lien avec le donateur</td>
            <td>{{ $achat->lien_avec_donateur }}</td>
        </tr>
        @endif
        <tr>
            <td class="label">Offert par</td>
            <td>{{ $achat->offertPar->nom }} {{ $achat->offertPar->prenom }} ({{ $achat->offertPar->identifiant_externe }})</td>
        </tr>
        @endif
        <tr>
            <td class="label">N° d'achat</td>
            <td>{{ $achat->numero_achat }}</td>
        </tr>
        <tr>
            <td class="label">Compte</td>
            <td>{{ $compte->numero_compte }} ({{ ucfirst($compte->categorie) }})</td>
        </tr>
        <tr>
            <td class="label">Type d'achat</td>
            <td>{{ ucfirst($achat->type_achat) }}</td>
        </tr>
        <tr>
            <td class="label">Nombre d'actions</td>
            <td>{{ number_format($achat->nombre_actions, 0, ',', ' ') }}</td>
        </tr>
        <tr>
            <td class="label">Prix unitaire</td>
            <td>{{ number_format($achat->prix_unitaire, 0, ',', ' ') }} CFA</td>
        </tr>
        <tr>
            <td class="label">Montant total</td>
            <td class="montant-total">{{ number_format($achat->montant, 0, ',', ' ') }} CFA</td>
        </tr>
        @if ($achat->mode_paiement)
        <tr>
            <td class="label">Mode de paiement</td>
            <td>{{ $achat->mode_paiement }}</td>
        </tr>
        @endif
        @if ($achat->reference_facture)
        <tr>
            <td class="label">Référence</td>
            <td>{{ $achat->reference_facture }}</td>
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
    </div>
</body>
</html>
