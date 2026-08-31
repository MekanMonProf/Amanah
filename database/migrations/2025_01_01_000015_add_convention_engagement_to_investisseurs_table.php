<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('investisseurs', function (Blueprint $table) {
            $table->string('convention_engagement_path')->nullable()->after('piece_identite_path');
            $table->date('date_signature_convention')->nullable()->after('convention_engagement_path');
        });
    }

    public function down(): void
    {
        Schema::table('investisseurs', function (Blueprint $table) {
            $table->dropColumn(['convention_engagement_path', 'date_signature_convention']);
        });
    }
};
