<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 25px 30px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1f2937; }
        .en-tete { border-bottom: 2px solid #047857; padding-bottom: 8px; margin-bottom: 14px; display: flex; }
        .en-tete .nom-projet { font-size: 16px; font-weight: bold; color: #047857; }
        .en-tete .sous-titre { font-size: 10px; color: #6b7280; }
        h1 { font-size: 13px; margin: 0 0 4px 0; }
        .meta { font-size: 9px; color: #6b7280; margin-bottom: 14px; }
        table { width: 100%; border-collapse: collapse; }
        th { background-color: #f3f4f6; text-align: left; padding: 5px 6px; border-bottom: 1px solid #d1d5db; font-size: 9px; }
        td { padding: 4px 6px; border-bottom: 1px solid #f3f4f6; font-size: 9.5px; }
        .badge { padding: 1px 6px; border-radius: 8px; font-size: 8.5px; }
        .badge-actif { background-color: #d1fae5; color: #047857; }
        .badge-inactif { background-color: #f3f4f6; color: #6b7280; }
        .pied-page { margin-top: 20px; padding-top: 8px; border-top: 1px solid #e5e7eb; font-size: 8px; color: #9ca3af; }
    </style>
</head>
<body>
    <div class="en-tete">
        @include('pdf.partials.en-tete')
    </div>

    <h1>Liste des investisseurs</h1>
    <div class="meta">
        {{ $investisseurs->count() }} investisseur(s) — édité le {{ $dateGeneration->translatedFormat('d F Y à H:i') }}
        @if ($filtresActifs)
            — Filtres actifs : {{ $filtresActifs }}
        @endif
    </div>

    <table>
        <thead>
            <tr>
                <th>Identifiant</th>
                <th>Nom</th>
                <th>Type</th>
                <th>Téléphone</th>
                <th>Pays</th>
                <th>Gestionnaire</th>
                <th>Statut</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($investisseurs as $inv)
                <tr>
                    <td>{{ $inv->identifiant_externe }}</td>
                    <td>{{ $inv->nom }} {{ $inv->prenom }}</td>
                    <td>{{ ucfirst($inv->type_personne) }}</td>
                    <td>{{ $inv->telephone }}</td>
                    <td>{{ $inv->pays }}</td>
                    <td>{{ $inv->gestionnaire?->user ? $inv->gestionnaire->user->nom . ' ' . $inv->gestionnaire->user->prenom : '—' }}</td>
                    <td>
                        <span class="badge {{ $inv->statut === 'actif' ? 'badge-actif' : 'badge-inactif' }}">{{ $inv->statut }}</span>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="pied-page">
        Document généré automatiquement par la plateforme AMANAH le {{ $dateGeneration->format('d/m/Y à H:i') }}.
    </div>
</body>
</html>
