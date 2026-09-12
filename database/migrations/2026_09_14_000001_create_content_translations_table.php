<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Translation Cache منفصل تماماً عن Business Data.
     * الأصل العربي يبقى في جداوله؛ هنا تُحفظ الترجمات العرضية فقط.
     * صف واحد لكل (entity, id, field, locale) مع بصمة المصدر للـinvalidation.
     */
    public function up(): void
    {
        Schema::create('content_translations', function (Blueprint $table) {
            $table->id();
            $table->string('translatable_type', 32); // report | note | submission
            $table->unsignedBigInteger('translatable_id');
            $table->string('field', 64); // title | description | observation | recommendations | ...
            $table->char('source_hash', 64); // sha256(source_text)
            $table->mediumText('source_text');
            $table->string('locale', 5)->default('en');
            $table->mediumText('translated_text');
            $table->timestamps();

            $table->unique(
                ['translatable_type', 'translatable_id', 'field', 'locale'],
                'ct_entity_field_locale_unique'
            );
            $table->index(['translatable_type', 'translatable_id', 'locale'], 'ct_entity_locale_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_translations');
    }
};
