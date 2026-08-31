<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AuditLog extends Model
{
    protected $table = 'audit_logs';

    public $timestamps = false;

    protected $fillable = [
        'user_id', 'action', 'entite', 'entite_id', 'donnees_avant', 'donnees_apres', 'ip_address',
    ];

    protected $casts = [
        'donnees_avant' => 'array',
        'donnees_apres' => 'array',
        'created_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Point d'entrée unique pour tracer une action sensible.
     *
     * @param  string  $action   ex: 'creation', 'modification', 'suppression', 'reinitialisation_mdp'
     * @param  string  $entite   nom lisible de l'entité concernée, ex: 'investisseur', 'achat', 'radiation'
     * @param  int|null  $entiteId
     * @param  array|null  $avant   état avant modification (uniquement les champs utiles, pas le modèle entier)
     * @param  array|null  $apres   état après modification
     */
    public static function enregistrer(string $action, string $entite, ?int $entiteId = null, ?array $avant = null, ?array $apres = null): void
    {
        static::create([
            'user_id' => Auth::id(),
            'action' => $action,
            'entite' => $entite,
            'entite_id' => $entiteId,
            'donnees_avant' => $avant,
            'donnees_apres' => $apres,
            'ip_address' => request()?->ip(),
            'created_at' => now(),
        ]);
    }
}
