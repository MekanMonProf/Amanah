<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Modification directe de l'ENUM (Doctrine DBAL n'est pas requis de cette façon)
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('direction','administrateur','gestionnaire','lecture','investisseur') NOT NULL DEFAULT 'gestionnaire'");

        Schema::table('investisseurs', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->unique()->after('id')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('investisseurs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });

        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('direction','administrateur','gestionnaire','lecture') NOT NULL DEFAULT 'gestionnaire'");
    }
};
