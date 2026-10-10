<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Les documents que la direction adresse à tout le monde.
 *
 * Les pièces jointes existantes appartiennent à un dossier : une carte
 * d'identité, un acte de décès, un justificatif de versement. Elles ne
 * regardent que leur titulaire et son gestionnaire, et App\Support\Document le
 * vérifie à chaque ouverture.
 *
 * Celles-ci sont l'inverse : une circulaire, un rapport annuel, un
 * procès-verbal d'assemblée s'adressent à l'ensemble des actionnaires et du
 * personnel. D'où une table à part plutôt qu'une colonne de plus quelque part —
 * ces documents n'ont pas de propriétaire, seulement un auteur.
 *
 * Le fichier vit sur le disque privé, comme les autres : il ne sort que par une
 * route qui demande d'abord qui vous êtes. « Visible par tous » veut dire par
 * tous ceux qui ont un compte, pas par le premier venu muni de l'adresse.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents_officiels', function (Blueprint $table) {
            $table->id();

            $table->string('titre', 200);
            $table->text('description')->nullable()
                ->comment("Ce que le document contient, pour qui hésite à l'ouvrir.");

            $table->string('fichier_path', 255)
                ->comment('Chemin sur le disque privé — jamais servi directement.');
            $table->string('nom_fichier', 255)
                ->comment("Le nom d'origine, rendu au téléchargement.");
            $table->unsignedBigInteger('taille')->comment('En octets, pour l\'annoncer avant le clic.');

            // Qui l'a publié, et quand. Un document officiel sans auteur serait
            // une rumeur ; la date fait foi auprès des actionnaires.
            $table->foreignId('publie_par')->nullable()->constrained('users')->nullOnDelete();
            $table->date('date_document')->nullable()
                ->comment("La date que porte le document, qui n'est pas celle du dépôt.");

            $table->timestamps();

            // L'écran les range du plus récent au plus ancien, sans autre filtre.
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents_officiels');
    }
};
