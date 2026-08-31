<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 25px 35px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1f2937; }
        .en-tete { border-bottom: 2px solid #047857; padding-bottom: 10px; margin-bottom: 18px; }
        .en-tete .nom-projet { font-size: 18px; font-weight: bold; color: #047857; }
        .en-tete .sous-titre { font-size: 11px; color: #6b7280; }
        .bloc-investisseur { margin-bottom: 16px; }
        .bloc-investisseur td { padding: 2px 0; }
        .label { color: #6b7280; width: 130px; }
        h2.compte-titre {
            font-size: 13px; background-color: #f3f4f6; padding: 6px 8px; margin: 18px 0 8px 0;
            border-left: 4px solid #047857;
        }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 10px; }
        .badge-commercial { background-color: #dbeafe; color: #1d4ed8; }
        .badge-waqf { background-color: #ede9fe; color: #6d28d9; }
        .resume { width: 100%; margin-bottom: 10px; }
        .resume td { padding: 4px 8px; }
        .resume .valeur { font-size: 15px; font-weight: bold; }
        table.donnees { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        table.donnees th { background-color: #f9fafb; text-align: left; padding: 5px 6px; border-bottom: 1px solid #e5e7eb; font-size: 10px; color: #6b7280; }
        table.donnees td { padding: 5px 6px; border-bottom: 1px solid #f3f4f6; font-size: 10.5px; }
        .text-right { text-align: right; }
        .text-vert { color: #047857; }
        .text-rouge { color: #dc2626; }
        .pied-page { margin-top: 25px; padding-top: 10px; border-top: 1px solid #e5e7eb; font-size: 9px; color: #9ca3af; }
    </style>
</head>
<body>

    <div class="en-tete">
        <div class="nom-projet">AMANAH</div>
        <div class="sous-titre">Plateforme de Gestion des Investissements — AND DOX S.A.</div>
    </div>

    <h1 style="font-size: 15px; margin-bottom: 6px;">Relevé de compte</h1>
    @if ($dateDebut || $dateFin)
        <p style="font-size: 11px; color: #6b7280; margin: 0 0 14px 0;">
            Période du {{ $dateDebut ? \Illuminate\Support\Carbon::parse($dateDebut)->translatedFormat('d F Y') : 'origine' }}
            au {{ $dateFin ? \Illuminate\Support\Carbon::parse($dateFin)->translatedFormat('d F Y') : "aujourd'hui" }}
        </p>
    @else
        <p style="font-size: 11px; color: #6b7280; margin: 0 0 14px 0;">Historique complet</p>
    @endif

    <table class="bloc-investisseur">
        <tr>
            <td class="label">Investisseur</td>
            <td><strong>{{ $investisseur->nom }} {{ $investisseur->prenom }}</strong></td>
        </tr>
        <tr>
            <td class="label">Identifiant</td>
            <td>{{ $investisseur->identifiant_externe }}</td>
        </tr>
        @if ($investisseur->telephone)
        <tr>
            <td class="label">Téléphone</td>
            <td>{{ $investisseur->telephone }}</td>
        </tr>
        @endif
        <tr>
            <td class="label">Date d'édition</td>
            <td>{{ $dateGeneration->translatedFormat('d F Y à H:i') }}</td>
        </tr>
    </table>

    @forelse ($comptes as $item)
        <h2 class="compte-titre">
            <span class="badge {{ $item['compte']->categorie === 'commercial' ? 'badge-commercial' : 'badge-waqf' }}">
                {{ ucfirst($item['compte']->categorie) }}
            </span>
            Compte {{ $item['compte']->numero_compte }}
        </h2>

        <table class="resume">
            <tr>
                <td style="width: 25%;">
                    Actions détenues (à ce jour)<br>
                    <span class="valeur">{{ number_format($item['nombre_actions'], 0, ',', ' ') }}</span>
                </td>
                @if ($dateDebut)
                <td style="width: 25%;">
                    Solde de départ<br>
                    <span class="valeur">{{ number_format($item['solde_avant'], 0, ',', ' ') }} CFA</span>
                </td>
                @endif
                <td>
                    {{ $dateFin ? 'Solde en fin de période' : 'Solde actuel' }}<br>
                    <span class="valeur text-vert">{{ number_format($item['solde_fin'], 0, ',', ' ') }} CFA</span>
                </td>
            </tr>
        </table>

        @if ($item['achats']->isNotEmpty())
            <table class="donnees">
                <thead>
                    <tr>
                        <th>N° achat</th>
                        <th>Date</th>
                        <th>Type</th>
                        <th class="text-right">Actions</th>
                        <th class="text-right">Montant</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($item['achats'] as $achat)
                        <tr>
                            <td>{{ $achat->numero_achat }}</td>
                            <td>{{ $achat->date_achat->format('d/m/Y') }}</td>
                            <td>{{ ucfirst($achat->type_achat) }}</td>
                            <td class="text-right">{{ number_format($achat->nombre_actions, 0, ',', ' ') }}</td>
                            <td class="text-right">{{ number_format($achat->montant, 0, ',', ' ') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        @if ($item['dividendes']->isNotEmpty())
            <table class="donnees">
                <thead>
                    <tr>
                        <th>Période</th>
                        <th class="text-right">Actions</th>
                        <th class="text-right">Bénéfice/action</th>
                        <th class="text-right">Montant</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($item['dividendes'] as $dividende)
                        <tr>
                            <td>{{ $dividende->periode->translatedFormat('F Y') }}</td>
                            <td class="text-right">{{ number_format($dividende->nombre_actions, 0, ',', ' ') }}</td>
                            <td class="text-right">{{ number_format($dividende->benefice_par_action, 0, ',', ' ') }}</td>
                            <td class="text-right">{{ number_format($dividende->montant_calcule, 0, ',', ' ') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        @if ($item['ecritures']->isNotEmpty())
            <table class="donnees">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th class="text-right">Montant</th>
                        <th class="text-right">Solde après</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($item['ecritures'] as $ecriture)
                        <tr>
                            <td>{{ $ecriture->date_ecriture->format('d/m/Y') }}</td>
                            <td>{{ str_replace('_', ' ', $ecriture->type_ecriture) }}</td>
                            <td class="text-right {{ $ecriture->montant >= 0 ? 'text-vert' : 'text-rouge' }}">
                                {{ $ecriture->montant >= 0 ? '+' : '' }}{{ number_format($ecriture->montant, 0, ',', ' ') }}
                            </td>
                            <td class="text-right">{{ number_format($ecriture->solde_apres, 0, ',', ' ') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @elseif ($dateDebut || $dateFin)
            <p style="font-size: 10px; color: #9ca3af;">Aucun mouvement sur cette période.</p>
        @endif
    @empty
        <p>Aucun compte d'investissement pour l'instant.</p>
    @endforelse

    <div class="pied-page">
        Document généré automatiquement par la plateforme AMANAH le {{ $dateGeneration->format('d/m/Y à H:i') }}.
        Ce relevé est fourni à titre indicatif et récapitule les opérations enregistrées à ce jour.
    </div>

</body>
</html>
