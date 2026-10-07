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
        .vide { padding: 14px 6px; color: #9ca3af; font-style: italic; }
        .pied-page { margin-top: 20px; padding-top: 8px; border-top: 1px solid #e5e7eb; font-size: 8px; color: #9ca3af; }
    </style>
</head>
<body>
    <div class="en-tete">
        @include('pdf.partials.en-tete')
    </div>

    <h1>Dons — {{ $compte->numero_compte }}</h1>
    <div class="meta">
        {{ $compte->investisseur->nom }} {{ $compte->investisseur->prenom }} ({{ $compte->investisseur->identifiant_externe }}) ·
        {{ $dons->count() }} don(s) — édité le {{ $dateGeneration->translatedFormat('d F Y à H:i') }}
    </div>

    <table>
        <thead>
            <tr>
                <th>Date</th><th>Sens</th><th>Type</th>
                <th>Contrepartie</th>
                <th class="text-right">Actions</th><th class="text-right">Montant</th>
                <th>Motif</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($dons as $d)
                @php($donne = $d->compte_source_id === $compte->id)
                @php($autre = $donne ? $d->compteDestinataire : $d->compteSource)
                <tr>
                    <td>{{ $d->date_don->format('d/m/Y') }}</td>
                    <td>{{ $donne ? 'Donné' : 'Reçu' }}</td>
                    <td>{{ $d->type_don === 'actions' ? 'Actions' : 'Solde' }}</td>
                    <td>
                        {{ trim(($autre->investisseur->nom ?? '') . ' ' . ($autre->investisseur->prenom ?? '')) }}
                        @if ($autre->investisseur->identifiant_externe ?? null)
                            ({{ $autre->investisseur->identifiant_externe }})
                        @endif
                    </td>
                    <td class="text-right">{{ $d->nombre_actions ? number_format($d->nombre_actions, 0, ',', ' ') : '—' }}</td>
                    <td class="text-right">{{ $d->montant ? number_format($d->montant, 0, ',', ' ') : '—' }}</td>
                    <td>{{ $d->motif }}</td>
                </tr>
            @empty
                {{-- Une page qui ne dit rien laisse croire à une erreur d'édition. --}}
                <tr><td colspan="7" class="vide">Aucun don sur la période demandée.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="pied-page">Document généré automatiquement par la plateforme AMANAH le {{ $dateGeneration->format('d/m/Y à H:i') }}.</div>
</body>
</html>
