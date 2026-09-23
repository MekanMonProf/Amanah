<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Radiation extends Model
{
    protected $fillable = [
        'compte_id', 'numero_radiation', 'date_radiation', 'nombre_actions_radiees',
        'prix_unitaire_action', 'montant_total', 'mode_paiement', 'reference_facture',
        'mois_previsionnel_paiement', 'piece_justificative_path', 'observations',
        'observation_cle', 'observation_parametres',
    ];

    protected $casts = [
        'date_radiation' => 'date',
        'mois_previsionnel_paiement' => 'date',
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
}
