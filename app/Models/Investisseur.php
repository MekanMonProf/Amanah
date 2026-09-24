<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Investisseur extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'identifiant_externe', 'type_personne', 'nom', 'prenom', 'telephone', 'whatsapp', 'email',
        'type_identification', 'numero_identification', 'date_delivrance_piece', 'lieu_delivrance_piece', 'date_expiration_piece',
        'pays', 'adresse', 'ville', 'date_naissance', 'lieu_naissance', 'nationalite',
        'raison_sociale', 'rccm', 'ninea', 'representant_legal_nom', 'representant_legal_telephone', 'representant_legal_whatsapp',
        'beneficiaire_nom', 'beneficiaire_lien', 'beneficiaire_telephone', 'beneficiaire_whatsapp',
        'piece_identite_path', 'convention_engagement_path', 'date_signature_convention', 'notes_internes',
        'gestionnaire_id', 'statut', 'date_deces', 'piece_acte_deces_path', 'succession_reglee',
    ];

    protected $casts = [
        'date_expiration_piece' => 'date',
        'date_delivrance_piece' => 'date',
        'date_naissance' => 'date',
        'date_signature_convention' => 'date',
        'date_deces' => 'date',
        'succession_reglee' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function gestionnaire()
    {
        return $this->belongsTo(Gestionnaire::class);
    }

    public function comptes()
    {
        return $this->hasMany(CompteInvestissement::class);
    }

    public function compteCommercial()
    {
        return $this->hasOne(CompteInvestissement::class)->where('categorie', 'commercial');
    }

    public function compteWaqf()
    {
        return $this->hasOne(CompteInvestissement::class)->where('categorie', 'waqf');
    }

    /**
     * Retourne le compte de la catégorie demandée, en le créant s'il n'existe pas encore.
     */
    public function compteOuCree(string $categorie): CompteInvestissement
    {
        $compte = $this->comptes()->where('categorie', $categorie)->first();

        if ($compte) {
            return $compte;
        }

        $suffixe = $categorie === 'commercial' ? 'COM' : 'WAQF';

        return $this->comptes()->create([
            'categorie' => $categorie,
            'numero_compte' => $this->identifiant_externe . '-' . $suffixe,
            'reinvestissement_auto' => true,
            'statut' => 'actif',
            'date_ouverture' => now()->toDateString(),
        ]);
    }

    public function historiqueAffectations()
    {
        return $this->hasMany(HistoriqueAffectation::class);
    }

    public function transfererVers(Gestionnaire $nouveauGestionnaire, ?string $motif = null, ?int $effectuePar = null): void
    {
        $ancienId = $this->gestionnaire_id;

        $this->historiqueAffectations()->create([
            'ancien_gestionnaire_id' => $ancienId,
            'nouveau_gestionnaire_id' => $nouveauGestionnaire->id,
            'date_transfert' => now()->toDateString(),
            'motif' => $motif,
            'effectue_par' => $effectuePar,
        ]);

        $this->update(['gestionnaire_id' => $nouveauGestionnaire->id]);
    }

    /**
     * Héritiers déclarés pour ce défunt (n'a de sens que si estDecede() est vrai).
     */
    public function heritiers()
    {
        return $this->hasMany(Heritier::class, 'investisseur_id');
    }

    public function estDecede(): bool
    {
        return $this->statut === 'decede';
    }

    /**
     * Un compte de ce défunt ne peut plus faire l'objet d'aucune opération courante
     * (achat, complément, paiement, don, radiation) — seule la répartition de succession
     * est autorisée, via un mécanisme dédié.
     */
    public function compteGele(): bool
    {
        return $this->estDecede();
    }

    public function sommeDesParts(): float
    {
        return (float) $this->heritiers()->sum('part_pourcentage');
    }

    /**
     * Prochain identifiant disponible (A0001, A0002...) — utilisé aussi bien à la création
     * rapide qu'à la création automatique du dossier d'un héritier lors d'une succession.
     */
    public static function prochainIdentifiant(): string
    {
        do {
            $dernier = static::where('identifiant_externe', 'like', 'A%')
                ->orderByRaw('CAST(SUBSTRING(identifiant_externe, 2) AS UNSIGNED) DESC')
                ->value('identifiant_externe');

            $prochainNumero = $dernier ? ((int) substr($dernier, 1)) + 1 : 1;
            $identifiant = 'A' . str_pad((string) $prochainNumero, 4, '0', STR_PAD_LEFT);
        } while (static::where('identifiant_externe', $identifiant)->exists());

        return $identifiant;
    }

    public const NOM_WAQF_CARITATIF = 'Waqf Dolel Xamxam';

    /**
     * Le compte institutionnel de l'œuvre caritative. Il reçoit deux flux : le capital Waqf
     * des successions (inaliénabilité du Waqf) et les achats offerts à la mémoire d'un défunt.
     * Créé à la volée pour ne pas dépendre d'un seeder.
     */
    public static function waqfCaritatif(): self
    {
        return static::firstOrCreate(
            ['nom' => self::NOM_WAQF_CARITATIF, 'type_personne' => 'morale'],
            [
                'identifiant_externe' => static::prochainIdentifiant(),
                'raison_sociale' => self::NOM_WAQF_CARITATIF,
                'statut' => 'actif',
                'notes_internes' => 'Compte institutionnel recevant automatiquement le capital Waqf des successions, conformément au principe d\'inaliénabilité du Waqf.',
            ]
        );
    }
}
