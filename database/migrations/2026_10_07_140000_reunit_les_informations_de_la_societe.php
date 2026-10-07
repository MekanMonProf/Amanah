<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Une seule table pour tout ce que la maison dit d'elle-même.
 *
 * Ces informations étaient jusqu'ici dans trois endroits : le `.env` pour le
 * numéro de support, `config/societe.php` qui le relayait, et des chaînes
 * écrites en dur dans l'en-tête des PDF. Changer de raison sociale demandait
 * donc de toucher au code et de redéployer, ce qui n'est pas un réglage.
 *
 * La table `parametres_support`, créée quelques heures plus tôt, est reprise
 * ici : deux tables de réglages dont l'une porte des coordonnées et l'autre
 * l'identité auraient divergé à la première modification.
 *
 * Tout est nullable et rien n'est obligatoire : une colonne vide laisse la
 * valeur de `config/societe.php` ou celle du gabarit s'appliquer, ce qui fait
 * que la mise à jour ne change rien tant que personne n'a rempli l'écran.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parametres_societe', function (Blueprint $table) {
            $table->id();

            // Identité
            $table->string('nom')->nullable()
                ->comment("Nom de la plateforme tel qu'il s'affiche — AMANAH.");
            $table->string('raison_sociale')->nullable()
                ->comment('Dénomination légale — AND DOX S.A.');
            $table->string('activite')->nullable()
                ->comment("Ce que fait la maison, en une ligne, sous le nom dans l'en-tête des PDF.");
            $table->string('oeuvre')->nullable()
                ->comment("Nom de l'œuvre caritative associée — Waqf Dolel Xamxam.");

            // Immatriculation
            $table->string('rccm', 60)->nullable();
            $table->string('ninea', 60)->nullable();

            // Où elle se trouve
            $table->string('adresse')->nullable();
            $table->string('ville', 120)->nullable();
            $table->string('pays', 120)->nullable();

            // Comment la joindre — c'est aussi ce que voit qui demande de l'aide
            $table->string('telephone', 30)->nullable();
            $table->string('whatsapp', 30)->nullable()
                ->comment('Numéro WhatsApp du support, si différent du téléphone.');
            $table->string('email')->nullable();
            $table->string('site_web')->nullable();
            $table->string('horaires')->nullable()
                ->comment("Quand le support répond, en clair — « du lundi au vendredi, 9h-17h ».");

            // Ce qui relève de l'aide et non de l'identité, repris tel quel
            $table->json('videos')->nullable()
                ->comment("code du sujet d'aide => adresse de la vidéo.");

            $table->timestamps();
        });

        // Reprise de la ligne unique de parametres_support, si elle existe.
        if (Schema::hasTable('parametres_support')) {
            $ancien = DB::table('parametres_support')->first();

            DB::table('parametres_societe')->insert([
                'telephone' => $ancien->telephone ?? null,
                'whatsapp' => $ancien->whatsapp ?? null,
                'email' => $ancien->email ?? null,
                'horaires' => $ancien->horaires ?? null,
                'videos' => $ancien->videos ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            Schema::drop('parametres_support');
        }
    }

    public function down(): void
    {
        Schema::create('parametres_support', function (Blueprint $table) {
            $table->id();
            $table->string('telephone', 30)->nullable();
            $table->string('whatsapp', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('horaires')->nullable();
            $table->json('videos')->nullable();
            $table->timestamps();
        });

        $societe = DB::table('parametres_societe')->first();

        if ($societe) {
            DB::table('parametres_support')->insert([
                'telephone' => $societe->telephone,
                'whatsapp' => $societe->whatsapp,
                'email' => $societe->email,
                'horaires' => $societe->horaires,
                'videos' => $societe->videos,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::dropIfExists('parametres_societe');
    }
};
