<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quand chacun a regardé les documents officiels pour la dernière fois.
 *
 * Il fallait prévenir d'une publication nouvelle. Aucun canal sortant n'existe
 * sur cette plateforme — aucun actionnaire n'a d'adresse email, WhatsApp passe
 * par des liens qu'un humain clique, et aucun fournisseur de SMS n'est
 * raccordé. La notification se fait donc là où les gens viennent : une pastille
 * sur l'entrée du menu, qui dit combien de documents ils n'ont pas encore vus.
 *
 * Une seule colonne suffit, et non une table de lecture par personne et par
 * document : la question posée est « combien depuis votre dernier passage »,
 * pas « lesquels exactement ». Un horodatage y répond, et ne grandit pas avec
 * le nombre d'actionnaires multiplié par celui des documents.
 *
 * Laissée à null, elle compte tout comme nouveau : pour qui n'a jamais ouvert
 * la page, tous les documents le sont en effet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('documents_officiels_vus_le')->nullable()
                ->comment('Dernier passage sur les documents officiels ; null = jamais.');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('documents_officiels_vus_le');
        });
    }
};
