<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La langue de l'investisseur, portée par son dossier et non par son accès portail.
 *
 * Jusqu'ici elle n'existait que sur `users.langue`, donc seulement pour la minorité
 * d'investisseurs à qui un accès a été ouvert. Or c'est une propriété de la personne :
 * elle décide de la langue du message WhatsApp qui transmet les identifiants — message
 * envoyé au moment même où le compte se crée, avant que `users.langue` ne veuille dire
 * quoi que ce soit — et elle doit pouvoir être notée dès la saisie du dossier.
 *
 * Le français par défaut : c'est la langue de travail de la société, et celle dans
 * laquelle tous les dossiers existants ont été tenus.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('investisseurs', function (Blueprint $table) {
            $table->enum('langue', ['fr', 'en', 'ar'])
                ->default('fr')
                ->after('nationalite');
        });
    }

    public function down(): void
    {
        Schema::table('investisseurs', function (Blueprint $table) {
            $table->dropColumn('langue');
        });
    }
};
