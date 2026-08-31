<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 25px 30px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1f2937; }
        .en-tete { border-bottom: 2px solid #047857; padding-bottom: 8px; margin-bottom: 14px; }
        .en-tete .nom-projet { font-size: 16px; font-weight: bold; color: #047857; }
        .en-tete .sous-titre { font-size: 10px; color: #6b7280; }
        h1 { font-size: 13px; margin: 0 0 2px 0; }
        .meta { font-size: 9px; color: #6b7280; margin-bottom: 14px; }
        table { width: 100%; border-collapse: collapse; }
        th { background-color: #f3f4f6; text-align: left; padding: 5px 6px; border-bottom: 1px solid #d1d5db; font-size: 9px; }
        td { padding: 4px 6px; border-bottom: 1px solid #f3f4f6; font-size: 9.5px; }
        .text-right { text-align: right; }
        .text-vert { color: #047857; }
        .text-rouge { color: #dc2626; }
        .pied-page { margin-top: 20px; padding-top: 8px; border-top: 1px solid #e5e7eb; font-size: 8px; color: #9ca3af; }
    </style>
</head>
<body>
    <div class="en-tete">
        <div class="nom-projet">AMANAH</div>
        <div class="sous-titre">Plateforme de Gestion des Investissements — AND DOX S.A.</div>
    </div>

    <h1>Écritures du compte financier — {{ $compte->numero_compte }}</h1>
    <div class="meta">
        {{ $compte->investisseur->nom }} {{ $compte->investisseur->prenom }} ({{ $compte->investisseur->identifiant_externe }}) ·
        {{ $ecritures->count() }} écriture(s), ordre chronologique réel — édité le {{ $dateGeneration->translatedFormat('d F Y à H:i') }}
    </div>

    <table>
        <thead>
            <tr>
                <th>Date</th><th>Type</th><th class="text-right">Montant</th>
                <th class="text-right">Solde après</th><th>Observations</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($ecritures as $e)
                <tr>
                    <td>{{ $e->date_ecriture->format('d/m/Y') }}</td>
                    <td>{{ str_replace('_', ' ', $e->type_ecriture) }}</td>
                    <td class="text-right {{ $e->montant >= 0 ? 'text-vert' : 'text-rouge' }}">
                        {{ $e->montant >= 0 ? '+' : '' }}{{ number_format($e->montant, 0, ',', ' ') }}
                    </td>
                    <td class="text-right">{{ number_format($e->solde_apres, 0, ',', ' ') }}</td>
                    <td>{{ $e->observations }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="pied-page">Document généré automatiquement par la plateforme AMANAH le {{ $dateGeneration->format('d/m/Y à H:i') }}.</div>
</body>
</html>
