<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('investisseurs', function (Blueprint $table) {
            $table->string('lieu_naissance', 150)->nullable()->after('date_naissance');
            $table->date('date_delivrance_piece')->nullable()->after('numero_identification');
            $table->string('lieu_delivrance_piece', 150)->nullable()->comment('Autorité / lieu émetteur, ex: Préfecture de Dakar')->after('date_delivrance_piece');
        });
    }

    public function down(): void
    {
        Schema::table('investisseurs', function (Blueprint $table) {
            $table->dropColumn(['lieu_naissance', 'date_delivrance_piece', 'lieu_delivrance_piece']);
        });
    }
};
