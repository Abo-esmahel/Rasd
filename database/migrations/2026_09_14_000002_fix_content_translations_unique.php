<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * مفتاح الـCache يشمل بصمة المصدر: report:9:{hash}:en.
     * نفس الحقل قد يظهر بصيغ عرض مختلفة (مقتطف/كامل) — كل بصمة تحتفظ بترجمتها.
     */
    public function up(): void
    {
        Schema::table('content_translations', function (Blueprint $table) {
            $table->dropUnique('ct_entity_field_locale_unique');
            $table->unique(
                ['translatable_type', 'translatable_id', 'field', 'locale', 'source_hash'],
                'ct_entity_field_locale_hash_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('content_translations', function (Blueprint $table) {
            $table->dropUnique('ct_entity_field_locale_hash_unique');
            $table->unique(
                ['translatable_type', 'translatable_id', 'field', 'locale'],
                'ct_entity_field_locale_unique'
            );
        });
    }
};
