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

    <h1>Export global — Toutes les radiations</h1>
    <div class="meta">{{ $radiations->count() }} radiation(s) — édité le {{ $dateGeneration->translatedFormat('d F Y à H:i') }}</div>

    <table>
        <thead>
            <tr>
                <th>Investisseur</th><th>ID</th><th>Compte</th><th>Cat.</th>
                <th>N° radiation</th><th>Date</th>
                <th class="text-right">Actions radiées</th><th class="text-right">Prix unit.</th><th class="text-right">Montant total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($radiations as $r)
                <tr>
                    <td>{{ $r->compte->investisseur->nom }} {{ $r->compte->investisseur->prenom }}</td>
                    <td>{{ $r->compte->investisseur->identifiant_externe }}</td>
                    <td>{{ $r->compte->numero_compte }}</td>
                    <td>{{ \App\Support\Libelles::categorie($r->compte->categorie) }}</td>
                    <td>{{ $r->numero_radiation }}</td>
                    <td>{{ $r->date_radiation->format('d/m/Y') }}</td>
                    <td class="text-right">{{ number_format($r->nombre_actions_radiees, 0, ',', ' ') }}</td>
                    <td class="text-right">{{ number_format($r->prix_unitaire_action, 0, ',', ' ') }}</td>
                    <td class="text-right">{{ number_format($r->montant_total, 0, ',', ' ') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="pied-page">Document généré automatiquement par la plateforme AMANAH le {{ $dateGeneration->format('d/m/Y à H:i') }}.</div>
</body>
</html>
