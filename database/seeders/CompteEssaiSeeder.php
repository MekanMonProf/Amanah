<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Un compte d'administration pour regarder l'application, rien de plus.
 *
 * Il n'ouvre aucun dossier, ne détient aucune action et n'entre dans aucun
 * total : c'est une porte d'entrée, pas une donnée. Le mot de passe suit la
 * convention de DonneesTestSeeder — ces identifiants ne valent que sur une
 * base de travail locale, et n'ont rien à faire sur une installation ouverte
 * au public.
 *
 * Pour voir le portail investisseur, ne pas créer un faux investisseur : se
 * connecter avec ce compte, ouvrir une fiche et cliquer « Réinitialiser le mot
 * de passe ». L'application affiche le mot de passe généré, et l'accès se
 * révoque ensuite depuis la même fiche.
 *
 * Usage : php artisan db:seed --class=CompteEssaiSeeder
 */
class CompteEssaiSeeder extends Seeder
{
    public const EMAIL = 'essai@local.test';
    public const MOT_DE_PASSE = 'password';

    public function run(): void
    {
        $compte = User::updateOrCreate(
            ['email' => self::EMAIL],
            [
                'nom' => 'Essai',
                'prenom' => 'Compte de démonstration',
                'password' => Hash::make(self::MOT_DE_PASSE),
                'role' => 'administrateur',
                'langue' => 'fr',
                'actif' => true,
                'doit_changer_mot_de_passe' => false,
                'deux_fa_actif' => false,
            ],
        );

        $this->command?->info('Compte d\'essai #' . $compte->id . ' : ' . self::EMAIL . ' / ' . self::MOT_DE_PASSE);
    }
}
