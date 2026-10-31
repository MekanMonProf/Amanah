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
        @include('pdf.partials.styles-recapitulatif')
    </style>
</head>
<body>
    <div class="en-tete">
        @include('pdf.partials.en-tete')
    </div>

    <h1>Attestation de versement</h1>

    <p class="texte">
        AND DOX S.A. atteste avoir versé à <strong>{{ $investisseur->nom }} {{ $investisseur->prenom }}</strong>
        (identifiant {{ $investisseur->identifiant_externe }}) la somme de
        <strong>{{ number_format(abs($ecriture->montant), 0, ',', ' ') }} CFA</strong>,
        le {{ $ecriture->date_ecriture->translatedFormat('d F Y') }},
        sur le compte {{ $compte->numero_compte }} ({{ \App\Support\Libelles::categorie($compte->categorie) }}).
    </p>

    <table class="details">
        <tr>
            <td class="label">Compte</td>
            <td>{{ $compte->numero_compte }} ({{ \App\Support\Libelles::categorie($compte->categorie) }})</td>
        </tr>
        <tr>
            <td class="label">Date du versement</td>
            <td>{{ $ecriture->date_ecriture->format('d/m/Y') }}</td>
        </tr>
        <tr>
            <td class="label">Montant versé</td>
            <td class="montant-total">{{ number_format(abs($ecriture->montant), 0, ',', ' ') }} CFA</td>
        </tr>
        @if ($ecriture->observations)
        <tr>
            <td class="label">Détails</td>
            <td>{{ $ecriture->observations }}</td>
        </tr>
        @endif
    </table>

    {{-- Les versements antérieurs. Quarante-deux comptes sur quatre-vingt-dix-neuf
         en ont reçu plusieurs : sans ce rappel, il fallait ouvrir le relevé pour
         savoir ce qui avait déjà été payé. --}}
    @if ($historique->count() > 1)
        <h2 class="titre-recap">Versements reçus sur ce compte</h2>

        <table class="recap">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Motif</th>
                    <th class="chiffre">Montant</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($historique as $ligne)
                    <tr class="{{ $ligne->id === $ecriture->id ? 'ligne-courante' : '' }}">
                        <td>{{ $ligne->date_ecriture->format('d/m/Y') }}</td>
                        <td>{{ $ligne->observation_affichee ?: '—' }}</td>
                        <td class="chiffre">{{ number_format(abs((float) $ligne->montant), 0, ',', ' ') }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="2">Total versé à cette date</td>
                    <td class="chiffre">{{ number_format($totalVerse, 0, ',', ' ') }} CFA</td>
                </tr>
            </tfoot>
        </table>

        {{-- Seuls les versements figurent ici. Un compte reçoit aussi des
             dividendes et des compléments, qui ne sont pas des sorties : les
             mêler fausserait le total, et le relevé mensuel dit déjà tout. --}}
        <p class="texte avertissement">
            Ce tableau ne retient que les versements à l'investisseur. Les dividendes portés
            au compte et les compléments figurent sur le relevé.
        </p>
    @endif

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
