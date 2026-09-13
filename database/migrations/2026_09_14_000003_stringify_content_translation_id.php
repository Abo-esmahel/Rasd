<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('content_translations');
        Schema::create('content_translations', function (Blueprint $table) {
            $table->id();
            $table->string('translatable_type', 32);
            $table->string('translatable_id', 64);
            $table->string('field', 64);
            $table->char('source_hash', 64);
            $table->mediumText('source_text');
            $table->string('locale', 5)->default('en');
            $table->mediumText('translated_text');
            $table->timestamps();

            $table->unique(
                ['translatable_type', 'translatable_id', 'field', 'locale', 'source_hash'],
                'ct_entity_field_locale_hash_unique'
            );
            $table->index(['translatable_type', 'translatable_id', 'locale'], 'ct_entity_locale_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_translations');
        Schema::create('content_translations', function (Blueprint $table) {
            $table->id();
            $table->string('translatable_type', 32);
            $table->unsignedBigInteger('translatable_id');
            $table->string('field', 64);
            $table->char('source_hash', 64);
            $table->mediumText('source_text');
            $table->string('locale', 5)->default('en');
            $table->mediumText('translated_text');
            $table->timestamps();

            $table->unique(
                ['translatable_type', 'translatable_id', 'field', 'locale', 'source_hash'],
                'ct_entity_field_locale_hash_unique'
            );
            $table->index(['translatable_type', 'translatable_id', 'locale'], 'ct_entity_locale_idx');
        });
    }
};
