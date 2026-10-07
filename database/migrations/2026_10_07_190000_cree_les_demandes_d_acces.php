<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * L'investisseur bloqué signale qu'il l'est, sans passer par le téléphone.
 *
 * L'écran de connexion accepte l'email ou le numéro ; le mot de passe oublié
 * n'acceptait que l'email. Un investisseur entré par son numéro — c'est-à-dire
 * la plupart — n'avait donc aucune porte de secours : il devait appeler son
 * gestionnaire, et attendre qu'il décroche.
 *
 * La plateforme ne peut pas lui envoyer un code elle-même : aucun fournisseur
 * SMS n'est branché (voir config/sms.php) et WhatsApp passe par un lien qu'un
 * humain ouvre. Ce que l'application sait faire, c'est porter la demande
 * jusqu'au gestionnaire du dossier, qui réinitialise d'un clic et envoie le
 * message. Le détour par un humain n'est pas une faiblesse du dispositif :
 * c'est lui qui vérifie que la personne au bout du fil est la bonne.
 *
 * Une demande ne s'enregistre que si le numéro correspond à un dossier. Sinon
 * la table deviendrait un dépotoir à numéros tapés au hasard, et l'écran dirait
 * par son silence lesquels existent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demandes_acces', function (Blueprint $table) {
            $table->id();

            $table->foreignId('investisseur_id')->constrained('investisseurs')->cascadeOnDelete();
            $table->string('telephone', 30)
                ->comment('Le numéro tel que normalisé au moment de la demande.');

            $table->enum('statut', ['nouvelle', 'traitee'])->default('nouvelle');
            $table->foreignId('traite_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('traite_le')->nullable();

            $table->timestamps();

            // Le tableau de bord compte les nouvelles ; la fiche cherche celles
            // d'un dossier. Les deux passent par cet index.
            $table->index(['statut', 'investisseur_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demandes_acces');
    }
};
