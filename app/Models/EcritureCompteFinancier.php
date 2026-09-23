<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EcritureCompteFinancier extends Model
{
    protected $table = 'ecritures_compte_financier';

    public $timestamps = false; // uniquement created_at, pas de modification possible

    protected $fillable = [
        'compte_id', 'type_ecriture', 'montant', 'solde_apres',
        'reference_type', 'reference_id', 'date_ecriture', 'observations',
        'piece_justificative_path', 'created_by',
        'observation_cle', 'observation_parametres',
    ];

    protected $casts = [
        'date_ecriture' => 'date',
        'observation_parametres' => 'array',
    ];

    /**
     * Texte à afficher pour l'observation. Traduit quand l'application l'a
     * écrite (une clé est rangée à côté), rendu tel quel quand une personne
     * l'a saisie — voir App\Support\Observation.
     */
    public function getObservationAfficheeAttribute(): string
    {
        return \App\Support\Observation::rendre(
            $this->observation_cle,
            $this->observation_parametres,
            $this->observations,
        );
    }


    public function compte()
    {
        return $this->belongsTo(CompteInvestissement::class, 'compte_id');
    }

    // Volontairement : aucune méthode update() n'est exposée pour le solde —
    // toute correction doit passer par une nouvelle écriture de type "ajustement".
}
