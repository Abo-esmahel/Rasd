<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            // الحقول المنظمة التي يعبئها الإنسان (يدوياً أو باعتماد ناتج الآلة)
            $table->longText('summary')->nullable()->after('content');
            $table->longText('recommendations')->nullable()->after('summary');
            // مخرجات الآلة الخام (لا تُنشر ولا تُرى إلا للكاتب)
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
