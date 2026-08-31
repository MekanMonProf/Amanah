<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('politiques_investissement', function (Blueprint $table) {
            $table->boolean('versement_capital_radiation_possible')->default(true)->after('versement_dividendes_possible');
        });

        // Par défaut, activé pour toutes les catégories existantes (Commercial ET Waqf) —
        // rembourser un capital radié est une décision distincte de celle des dividendes.
        DB::table('politiques_investissement')->update(['versement_capital_radiation_possible' => true]);
    }

    public function down(): void
    {
        Schema::table('politiques_investissement', function (Blueprint $table) {
            $table->dropColumn('versement_capital_radiation_possible');
        });
    }
};
