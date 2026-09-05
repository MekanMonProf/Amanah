<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Colonnes de la double authentification. Le middleware (VerifierDeuxFa), le composant
 * de gestion (GererDeuxFa), l'écran de vérification (VerifierDeuxFaCode) et le modèle
 * User les référençaient déjà, mais aucune migration ne les créait : le bloc 2FA du
 * profil s'affichait donc à tout utilisateur et échouait en erreur SQL dès l'activation.
 *
 * Secret et codes de récupération sont chiffrés par Eloquent (casts 'encrypted' et
 * 'encrypted:array' dans User) — d'où le type text, la valeur chiffrée étant bien plus
 * longue que la donnée d'origine.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('deux_fa_secret')->nullable()->after('doit_changer_mot_de_passe');
            $table->boolean('deux_fa_actif')->default(false)->after('deux_fa_secret');
            $table->timestamp('deux_fa_confirme_le')->nullable()->after('deux_fa_actif');
            $table->text('deux_fa_codes_recuperation')->nullable()->after('deux_fa_confirme_le');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'deux_fa_secret',
                'deux_fa_actif',
                'deux_fa_confirme_le',
                'deux_fa_codes_recuperation',
            ]);
        });
    }
};
