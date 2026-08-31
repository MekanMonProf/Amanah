<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('heritiers', function (Blueprint $table) {
            $table->string('piece_certificat_heredite_path')->nullable()->after('piece_justificative_path');
        });
    }

    public function down(): void
    {
        Schema::table('heritiers', function (Blueprint $table) {
            $table->dropColumn('piece_certificat_heredite_path');
        });
    }
};
