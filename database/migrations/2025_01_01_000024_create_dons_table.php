<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE ecritures_compte_financier MODIFY COLUMN type_ecriture ENUM('dividende','versement_complementaire','achat_action','paiement','radiation','ajustement','don_sortant','don_entrant') NOT NULL");

        Schema::create('dons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('compte_source_id')->constrained('comptes_investissement');
            $table->foreignId('compte_destinataire_id')->constrained('comptes_investissement');
            $table->enum('type_don', ['actions', 'solde']);
            $table->unsignedInteger('nombre_actions')->nullable();
            $table->decimal('prix_unitaire_action', 15, 2)->nullable();
            $table->decimal('montant', 15, 2)->nullable();
            $table->date('date_don');
            $table->text('motif');
            $table->string('piece_justificative_path')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dons');
        DB::statement("ALTER TABLE ecritures_compte_financier MODIFY COLUMN type_ecriture ENUM('dividende','versement_complementaire','achat_action','paiement','radiation','ajustement') NOT NULL");
    }
};
