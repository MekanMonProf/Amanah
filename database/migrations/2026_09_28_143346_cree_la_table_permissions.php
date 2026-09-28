<?php

use App\Support\Modules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Qui accède à quoi, et jusqu'où — une ligne par couple rôle/module.
 *
 * La table est peuplée dès la migration avec la grille qui reproduit les droits
 * en vigueur jusqu'ici : la mise à jour ne change donc rien pour personne, et
 * l'écran de paramétrage s'ouvre sur l'état existant plutôt que sur une page
 * blanche qu'il faudrait remplir avant que l'application redevienne utilisable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('role', 30);
            $table->string('module', 40);
            $table->enum('niveau', [Modules::AUCUN, Modules::LECTURE, Modules::ECRITURE])
                ->default(Modules::AUCUN);
            $table->timestamps();

            $table->unique(['role', 'module']);
        });

        $maintenant = now();
        $lignes = [];

        foreach (Modules::grilleInitiale() as $role => $modules) {
            foreach ($modules as $module => $niveau) {
                $lignes[] = [
                    'role' => $role,
                    'module' => $module,
                    'niveau' => $niveau,
                    'created_at' => $maintenant,
                    'updated_at' => $maintenant,
                ];
            }
        }

        DB::table('permissions')->insert($lignes);
    }

    public function down(): void
    {
        Schema::dropIfExists('permissions');
    }
};
