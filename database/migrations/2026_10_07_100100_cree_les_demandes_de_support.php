<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Les demandes de support, enregistrées plutôt que seulement transmises.
 *
 * WhatsApp fait passer le message tout de suite, mais il ne garde rien
 * d'exploitable : une conversation n'est ni une file d'attente ni un historique.
 * La demande est donc écrite ici d'abord ; le message WhatsApp qui suit ne fait
 * que prévenir.
 *
 * `url_origine` retient l'écran d'où part la demande. La question « où étiez-vous
 * quand ça a coincé ? » est la première qu'on pose et la plus mal répondue.
 *
 * Le lien vers l'utilisateur est en nullOnDelete : une demande survit au compte
 * qui l'a posée, le compte ayant toutes les chances d'être désactivé avant que
 * la demande ne soit archivée.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demandes_support', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('role', 20)->nullable()
                ->comment('Rôle au moment de la demande : il dit à qui elle s\'adressait, même si le rôle change après.');

            $table->string('categorie', 40)
                ->comment('Famille de la demande, parmi celles du formulaire : question, anomalie, correction, acces, autre.');
            $table->string('sujet', 150);
            $table->text('message');
            $table->string('url_origine')->nullable()
                ->comment('Écran depuis lequel la demande est partie.');

            $table->enum('statut', ['nouvelle', 'en_cours', 'traitee'])->default('nouvelle');
            $table->text('reponse')->nullable();
            $table->foreignId('traite_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('traite_le')->nullable();

            $table->timestamps();

            // La liste du support se lit par statut puis par ancienneté : les
            // nouvelles d'abord, les plus anciennes en tête de chaque paquet.
            $table->index(['statut', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demandes_support');
    }
};
