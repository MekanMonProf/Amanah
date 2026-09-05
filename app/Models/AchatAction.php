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
        'offert_par_investisseur_id', 'type_offrande', 'offrande_pour', 'lien_avec_donateur', 'offrande_pour_investisseur_id',
    ];

    protected $casts = ['date_achat' => 'date'];

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
     * Nul pour une personne extérieure : seul offrande_pour est alors renseigné.
     */
    public function offrandePourInvestisseur()
    {
        return $this->belongsTo(Investisseur::class, 'offrande_pour_investisseur_id');
    }

    /** L'achat est une offrande : payé par un donateur, comptabilisé au Waqf caritatif. */
    public function estOffrande(): bool
    {
        return $this->offert_par_investisseur_id !== null;
    }

    /** Hommage à un défunt (par opposition à un cadeau fait à une personne vivante). */
    public function estEnMemoire(): bool
    {
        return $this->estOffrande() && $this->type_offrande === 'memoire';
    }

    /**
     * « à la mémoire de » pour un défunt, « au profit de » pour un cadeau à un vivant :
     * la formule sert aussi bien aux attestations qu'aux écrans.
     */
    public function formuleOffrande(): string
    {
        return $this->estEnMemoire() ? 'à la mémoire de' : 'au profit de';
    }

    /**
     * La même formule en début de phrase. Passe par Str::ucfirst et non ucfirst() :
     * ce dernier travaille octet par octet et mutile le « à » accentué en UTF-8.
     */
    public function formuleOffrandeMajuscule(): string
    {
        return \Illuminate\Support\Str::ucfirst($this->formuleOffrande());
    }
}
