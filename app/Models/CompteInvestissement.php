<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompteInvestissement extends Model
{
    use HasFactory;

    protected $table = 'comptes_investissement';

    protected $fillable = [
        'investisseur_id', 'categorie', 'numero_compte',
        'reinvestissement_auto', 'statut', 'date_ouverture', 'date_radiation',
    ];

    protected $casts = [
        'reinvestissement_auto' => 'boolean',
        'date_ouverture' => 'date',
        'date_radiation' => 'date',
    ];

    public function investisseur()
    {
        return $this->belongsTo(Investisseur::class);
    }

    public function achats()
    {
        return $this->hasMany(AchatAction::class, 'compte_id');
    }

    public function dividendes()
    {
        return $this->hasMany(Dividende::class, 'compte_id');
    }

    public function radiations()
    {
        return $this->hasMany(Radiation::class, 'compte_id');
    }

    public function donsEmis()
    {
        return $this->hasMany(Don::class, 'compte_source_id');
    }

    public function donsRecus()
    {
        return $this->hasMany(Don::class, 'compte_destinataire_id');
    }

    /**
     * Triées par ordre d'insertion (id) par défaut — c'est la vraie séquence chronologique
     * du solde qui s'accumule, contrairement à date_ecriture qui est une date "métier".
     * Pour trier autrement ailleurs, toujours utiliser ->reorder(...) avant, sinon les deux
     * ORDER BY s'empilent et celui-ci (id asc) reste prioritaire.
     */
    public function ecritures()
    {
        return $this->hasMany(EcritureCompteFinancier::class, 'compte_id')->orderBy('id');
    }

    public function politique()
    {
        return PolitiqueInvestissement::where('categorie', $this->categorie)->first();
    }

    /**
     * Nombre d'actions détenues à ce jour : achats - radiations - dons émis (actions) + dons reçus (actions).
     * Ne jamais stocker ce nombre : toujours le recalculer depuis l'historique.
     */
    public function nombreActions(): int
    {
        $achetees = $this->achats()->sum('nombre_actions');
        $radiees = $this->radiations()->sum('nombre_actions_radiees');
        $donneesEnActions = $this->donsEmis()->where('type_don', 'actions')->sum('nombre_actions');
        $recuesEnActions = $this->donsRecus()->where('type_don', 'actions')->sum('nombre_actions');

        return (int) ($achetees - $radiees - $donneesEnActions + $recuesEnActions);
    }

    /**
     * Solde du compte financier : toujours dérivé de la DERNIÈRE écriture insérée.
     * reorder() efface le tri par défaut de la relation (id asc) avant d'appliquer id desc.
     */
    public function solde(): float
    {
        $derniere = $this->ecritures()->reorder('id', 'desc')->first();

        return $derniere ? (float) $derniere->solde_apres : 0.0;
    }

    /**
     * Enregistre une écriture et fige le nouveau solde.
     * C'est l'UNIQUE point d'entrée pour modifier le solde d'un compte.
     */
    public function ajouterEcriture(string $type, float $montant, string $dateEcriture, ?string $referenceType = null, ?int $referenceId = null, ?string $observations = null, ?int $userId = null, ?string $pieceJustificativePath = null): EcritureCompteFinancier
    {
        $nouveauSolde = $this->solde() + $montant;

        return $this->ecritures()->create([
            'type_ecriture' => $type,
            'montant' => $montant,
            'solde_apres' => $nouveauSolde,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'date_ecriture' => $dateEcriture,
            'observations' => $observations,
            'piece_justificative_path' => $pieceJustificativePath,
            'created_by' => $userId,
        ]);
    }

    /**
     * Génère un numéro d'achat garanti unique, même si plusieurs achats automatiques
     * sont créés à la même seconde.
     */
    protected function genererNumeroAchatUnique(string $prefixe): string
    {
        do {
            $numero = $prefixe . '-' . now()->format('YmdHis') . '-' . $this->id . '-' . random_int(100, 999);
        } while (AchatAction::where('numero_achat', $numero)->exists());

        return $numero;
    }

    /**
     * Logique commune : achète autant d'actions que le solde le permet, tant qu'il en couvre
     * au moins une. Utilisée par le réinvestissement automatique et le complément financier.
     */
    public function acheterActionsAvecSoldeDisponible(string $typeAchat, string $observation, ?int $userId = null): int
    {
        $politique = $this->politique();
        $prixAction = (float) $politique->prix_unitaire_action;

        if ($prixAction <= 0) {
            return 0;
        }

        $nbAchatsRealises = 0;
        $garantieFou = 0;

        while ($this->solde() >= $prixAction && $garantieFou < 1000) {
            $garantieFou++;

            $nbActions = intdiv((int) floor($this->solde()), (int) $prixAction);
            if ($nbActions < 1) {
                break;
            }

            $montant = $nbActions * $prixAction;

            $achat = $this->achats()->create([
                'numero_achat' => $this->genererNumeroAchatUnique('AUTO'),
                'date_achat' => now()->toDateString(),
                'type_achat' => $typeAchat,
                'nombre_actions' => $nbActions,
                'prix_unitaire' => $prixAction,
                'montant' => $montant,
                'observations' => $observation,
            ]);

            $this->ajouterEcriture(
                type: 'achat_action',
                montant: -$montant,
                dateEcriture: now()->toDateString(),
                referenceType: 'achats_actions',
                referenceId: $achat->id,
                observations: $observation,
                userId: $userId,
            );

            $nbAchatsRealises++;
        }

        return $nbAchatsRealises;
    }

    public function tenterReinvestissementAutomatique(?int $userId = null, ?string $observationPersonnalisee = null): void
    {
        if (! $this->reinvestissement_auto) {
            return;
        }

        $this->acheterActionsAvecSoldeDisponible('benefice', $observationPersonnalisee ?? 'Réinvestissement automatique', $userId);
    }
}
