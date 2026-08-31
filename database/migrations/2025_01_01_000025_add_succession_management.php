<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE investisseurs MODIFY COLUMN statut ENUM('actif','inactif','decede') NOT NULL DEFAULT 'actif'");

        Schema::table('investisseurs', function (Blueprint $table) {
            $table->date('date_deces')->nullable()->after('statut');
            $table->string('piece_acte_deces_path')->nullable()->after('date_deces');
            $table->boolean('succession_reglee')->default(false)->after('piece_acte_deces_path');
        });

        Schema::create('heritiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('investisseur_id')->constrained('investisseurs')->comment('le défunt');
            $table->foreignId('investisseur_heritier_id')->nullable()->constrained('investisseurs')->comment('dossier investisseur créé/lié pour recevoir la part');
            $table->string('nom', 150);
            $table->string('prenom', 150)->nullable();
            $table->string('telephone', 30)->nullable();
            $table->string('lien_parente', 100)->nullable();
            $table->decimal('part_pourcentage', 5, 2)->comment('part en % de la succession, somme des héritiers = 100');
            $table->string('piece_identite_path')->nullable();
            $table->string('piece_justificative_path')->nullable()->comment('jugement d\'hérédité ou équivalent');
            $table->timestamps();
        });

        Schema::table('dons', function (Blueprint $table) {
            $table->enum('type_operation', ['don', 'succession'])->default('don')->after('type_don');
            $table->foreignId('heritier_id')->nullable()->constrained('heritiers')->after('type_operation');
        });
    }

    public function down(): void
    {
        Schema::table('dons', function (Blueprint $table) {
            $table->dropConstrainedForeignId('heritier_id');
            $table->dropColumn('type_operation');
        });

        Schema::dropIfExists('heritiers');

        Schema::table('investisseurs', function (Blueprint $table) {
            $table->dropColumn(['date_deces', 'piece_acte_deces_path', 'succession_reglee']);
        });

        DB::statement("ALTER TABLE investisseurs MODIFY COLUMN statut ENUM('actif','inactif') NOT NULL DEFAULT 'actif'");
    }
};
