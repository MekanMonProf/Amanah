<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'nom', 'prenom', 'email', 'telephone', 'password', 'role', 'langue', 'actif', 'doit_changer_mot_de_passe',
        'deux_fa_secret', 'deux_fa_actif', 'deux_fa_confirme_le', 'deux_fa_codes_recuperation',
    ];

    protected $hidden = [
        'password', 'remember_token', 'deux_fa_secret', 'deux_fa_codes_recuperation',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'actif' => 'boolean',
            'doit_changer_mot_de_passe' => 'boolean',
            'deux_fa_secret' => 'encrypted',
            'deux_fa_actif' => 'boolean',
            'deux_fa_confirme_le' => 'datetime',
            'deux_fa_codes_recuperation' => 'encrypted:array',
        ];
    }

    public function gestionnaire()
    {
        return $this->hasOne(Gestionnaire::class);
    }

    public function investisseurLie()
    {
        return $this->hasOne(Investisseur::class, 'user_id');
    }

    public function estDirection(): bool
    {
        return $this->role === 'direction';
    }

    public function estAdministrateur(): bool
    {
        return in_array($this->role, ['direction', 'administrateur'], true);
    }

    public function estGestionnaire(): bool
    {
        return $this->role === 'gestionnaire';
    }

    public function estInvestisseur(): bool
    {
        return $this->role === 'investisseur';
    }

    /**
     * Remplace l'email "mot de passe oublié" par défaut de Laravel (qui utilise un système
     * de composants ayant posé problème dans cet environnement) par notre version HTML en français.
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new \App\Notifications\ReinitialisationMotDePasseNotification($token));
    }
}
