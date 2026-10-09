<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pdf_documents', function (Blueprint $table) {
            $table->id();
            $table->string('documentable_type', 120);
            $table->unsignedBigInteger('documentable_id');
            $table->string('locale', 8)->default('ar');
            $table->string('content_hash', 64)->nullable();
            $table->string('status', 16)->default('pending');
            $table->string('file_path')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->text('error')->nullable();
            $table->timestamp('generating_since')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->unique(['documentable_type', 'documentable_id', 'locale'], 'pdf_documents_owner_unique');
            $table->index(['status', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pdf_documents');
    }
};
