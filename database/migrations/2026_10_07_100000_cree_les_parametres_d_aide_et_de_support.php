<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Où écrire quand on est bloqué, et quelles vidéos accompagnent l'aide.
 *
 * Une seule ligne, comme les paramètres de dividende : ce sont des réglages de
 * la maison, pas des données. Les coordonnées ne sont pas écrites en dur dans le
 * code — elles changent avec la personne qui tient le support, et personne ne
 * devrait avoir à toucher au code pour ça.
 *
 * Les adresses des vidéos vivent dans une colonne JSON plutôt que dans une table
 * à part : il y en a une par sujet d'aide, le catalogue des sujets est dans le
 * code, et une table de dix lignes dont les clés sont déjà connues n'apporterait
 * qu'une jointure.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parametres_support', function (Blueprint $table) {
            $table->id();

            $table->string('telephone', 30)->nullable()
                ->comment('Numéro du support de la plateforme, au format international.');
            $table->string('whatsapp', 30)->nullable()
                ->comment('Numéro WhatsApp du support, si différent du téléphone.');
            $table->string('email')->nullable()
                ->comment('Adresse du support de la plateforme.');
            $table->string('horaires')->nullable()
                ->comment('Quand le support répond, en clair — « du lundi au vendredi, 9h-17h ».');

            $table->json('videos')->nullable()
                ->comment('code du sujet d\'aide => adresse de la vidéo. Un sujet absent n\'affiche aucun emplacement.');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parametres_support');
    }
};
