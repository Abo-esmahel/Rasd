<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_translations', function (Blueprint $table) {
            // Optimized for resolveStoredMany(): WHERE type + locale + IN(id, field, hash)
            $table->index(
                ['translatable_type', 'locale', 'translatable_id', 'field', 'source_hash'],
                'ct_lookup_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('content_translations', function (Blueprint $table) {
            $table->dropIndex('ct_lookup_idx');
        });
    }
};
