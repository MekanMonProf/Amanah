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
        'offert_par_investisseur_id', 'en_memoire_de', 'lien_avec_donateur', 'en_memoire_investisseur_id',
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
     * Le défunt honoré, quand il s'agit d'un investisseur de la plateforme.
     * Nul pour une personne extérieure : seul en_memoire_de est alors renseigné.
     */
    public function enMemoireInvestisseur()
    {
        return $this->belongsTo(Investisseur::class, 'en_memoire_investisseur_id');
    }

    public function estEnMemoire(): bool
    {
        return $this->offert_par_investisseur_id !== null;
    }
}
