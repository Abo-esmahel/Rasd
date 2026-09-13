<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->longText('summary')->nullable()->after('content');
            $table->longText('recommendations')->nullable()->after('summary');
            $table->longText('ai_summary')->nullable()->after('ai_draft_content');
            $table->longText('ai_recommendations')->nullable()->after('ai_summary');
        });
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->dropColumn(['summary', 'recommendations', 'ai_summary', 'ai_recommendations']);
        });
    }
};
