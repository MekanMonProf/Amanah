<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AchatAction extends Model
{
    use HasFactory;

    protected $table = 'achats_actions';

    protected $fillable = [
        'compte_id', 'numero_achat', 'date_achat', 'type_achat', 'nombre_actions',
        'prix_unitaire', 'montant', 'mode_paiement', 'reference_facture',
        'photo_facture_path', 'observations', 'saisi_par',
        'offert_par_investisseur_id', 'type_present', 'present_pour', 'lien_avec_donateur', 'present_pour_investisseur_id',
        'observation_cle', 'observation_parametres',
    ];

    protected $casts = [
        'date_achat' => 'date',
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

    /**
     * Le donateur qui a payé l'achat. Les actions, elles, sont sur le compte institutionnel
     * du Waqf caritatif — d'où la distinction avec compte->investisseur.
     */
    public function offertPar()
    {
        return $this->belongsTo(Investisseur::class, 'offert_par_investisseur_id');
    }

    /**
     * La personne honorée, quand il s'agit d'un investisseur de la plateforme.
     * Nul pour une personne extérieure : seul present_pour est alors renseigné.
     */
    public function presentPourInvestisseur()
    {
        return $this->belongsTo(Investisseur::class, 'present_pour_investisseur_id');
    }

    /** L'achat est une present : payé par un donateur, comptabilisé au Waqf caritatif. */
    public function estUnPresent(): bool
    {
        return $this->offert_par_investisseur_id !== null;
    }

    /** Hommage à un défunt (par opposition à un cadeau fait à une personne vivante). */
    public function estEnMemoire(): bool
    {
        return $this->estUnPresent() && $this->type_present === 'memoire';
    }

    /**
     * « à la mémoire de » pour un défunt, « au profit de » pour un cadeau à un vivant :
     * la formule sert aussi bien aux attestations qu'aux écrans.
     */
    public function formulePresent(): string
    {
        return $this->estEnMemoire() ? 'à la mémoire de' : 'au profit de';
    }

    /**
     * La même formule en début de phrase. Passe par Str::ucfirst et non ucfirst() :
     * ce dernier travaille octet par octet et mutile le « à » accentué en UTF-8.
     */
    public function formulePresentMajuscule(): string
    {
        return \Illuminate\Support\Str::ucfirst($this->formulePresent());
    }
}
