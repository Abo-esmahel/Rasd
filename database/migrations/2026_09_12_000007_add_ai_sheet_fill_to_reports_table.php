<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->string('ai_sheet_image_path', 255)->nullable()->after('ai_recommendations');
            $table->dateTime('ai_sheet_generated_at')->nullable()->after('ai_sheet_image_path');
            $table->string('ai_sheet_data_hash', 64)->nullable()->after('ai_sheet_generated_at');
        });
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->dropColumn(['ai_sheet_image_path', 'ai_sheet_generated_at', 'ai_sheet_data_hash']);
        });
    }
};
