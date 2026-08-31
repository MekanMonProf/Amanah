<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ecritures_compte_financier', function (Blueprint $table) {
            $table->id();
            $table->foreignId('compte_id')->constrained('comptes_investissement');
            $table->enum('type_ecriture', [
                'dividende', 'versement_complementaire', 'achat_action',
                'paiement', 'radiation', 'ajustement',
            ]);
            $table->decimal('montant', 15, 2)->comment('positif = crédit, négatif = débit');
            $table->decimal('solde_apres', 15, 2)
                  ->comment('calculé et figé au moment de l’écriture, jamais modifié directement');
            $table->string('reference_type', 50)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->date('date_ecriture');
            $table->text('observations')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['compte_id', 'date_ecriture']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ecritures_compte_financier');
    }
};
