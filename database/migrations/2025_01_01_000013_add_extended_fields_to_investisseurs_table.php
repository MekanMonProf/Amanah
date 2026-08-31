<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('investisseurs', function (Blueprint $table) {
            // Contact
            $table->string('email', 190)->nullable()->after('telephone');
            $table->string('adresse')->nullable()->after('pays');
            $table->string('ville', 100)->nullable()->after('adresse');
            $table->date('date_naissance')->nullable()->after('ville');
            $table->string('nationalite', 100)->nullable()->after('date_naissance');

            // Personne morale (entreprise)
            $table->string('raison_sociale')->nullable()->after('nationalite');
            $table->string('rccm', 100)->nullable()->after('raison_sociale');
            $table->string('ninea', 100)->nullable()->after('rccm');
            $table->string('representant_legal_nom', 150)->nullable()->after('ninea');
            $table->string('representant_legal_telephone', 30)->nullable()->after('representant_legal_nom');

            // Succession / bénéficiaire désigné
            $table->string('beneficiaire_nom', 150)->nullable()->after('representant_legal_telephone');
            $table->string('beneficiaire_lien', 100)->nullable()->comment('ex: Épouse, Fils, Frère...')->after('beneficiaire_nom');
            $table->string('beneficiaire_telephone', 30)->nullable()->after('beneficiaire_lien');

            // Document et suivi interne
            $table->string('piece_identite_path')->nullable()->after('beneficiaire_telephone');
            $table->text('notes_internes')->nullable()->comment('Visible uniquement par gestionnaires/administrateurs')->after('piece_identite_path');
        });
    }

    public function down(): void
    {
        Schema::table('investisseurs', function (Blueprint $table) {
            $table->dropColumn([
                'email', 'adresse', 'ville', 'date_naissance', 'nationalite',
                'raison_sociale', 'rccm', 'ninea', 'representant_legal_nom', 'representant_legal_telephone',
                'beneficiaire_nom', 'beneficiaire_lien', 'beneficiaire_telephone',
                'piece_identite_path', 'notes_internes',
            ]);
        });
    }
};
