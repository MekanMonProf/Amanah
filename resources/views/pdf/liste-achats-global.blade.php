<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 20px 25px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #1f2937; }
        .en-tete { border-bottom: 2px solid #047857; padding-bottom: 6px; margin-bottom: 12px; }
        .en-tete .nom-projet { font-size: 15px; font-weight: bold; color: #047857; }
        .en-tete .sous-titre { font-size: 9px; color: #6b7280; }
        h1 { font-size: 12px; margin: 0 0 2px 0; }
        .meta { font-size: 8px; color: #6b7280; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th { background-color: #f3f4f6; text-align: left; padding: 4px 5px; border-bottom: 1px solid #d1d5db; font-size: 8px; }
        td { padding: 3px 5px; border-bottom: 1px solid #f3f4f6; font-size: 8.5px; }
        .text-right { text-align: right; }
        .pied-page { margin-top: 15px; padding-top: 6px; border-top: 1px solid #e5e7eb; font-size: 7px; color: #9ca3af; }
    </style>
</head>
<body>
    <div class="en-tete">
        @include('pdf.partials.en-tete')
    </div>

    <h1>Export global — Tous les achats</h1>
    <div class="meta">{{ $achats->count() }} achat(s) — édité le {{ $dateGeneration->translatedFormat('d F Y à H:i') }}</div>

    <table>
        <thead>
            <tr>
                <th>Investisseur</th><th>ID</th><th>Compte</th><th>Cat.</th>
                <th>N° achat</th><th>Date</th><th>Type</th>
                <th class="text-right">Actions</th><th class="text-right">Prix unit.</th><th class="text-right">Montant</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($achats as $a)
                <tr>
                    <td>{{ $a->compte->investisseur->nom }} {{ $a->compte->investisseur->prenom }}</td>
                    <td>{{ $a->compte->investisseur->identifiant_externe }}</td>
                    <td>{{ $a->compte->numero_compte }}</td>
                    <td>{{ ucfirst($a->compte->categorie) }}</td>
                    <td>{{ $a->numero_achat }}</td>
                    <td>{{ $a->date_achat->format('d/m/Y') }}</td>
                    <td>{{ ucfirst($a->type_achat) }}</td>
                    <td class="text-right">{{ number_format($a->nombre_actions, 0, ',', ' ') }}</td>
                    <td class="text-right">{{ number_format($a->prix_unitaire, 0, ',', ' ') }}</td>
                    <td class="text-right">{{ number_format($a->montant, 0, ',', ' ') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="pied-page">Document généré automatiquement par la plateforme AMANAH le {{ $dateGeneration->format('d/m/Y à H:i') }}.</div>
</body>
</html>
