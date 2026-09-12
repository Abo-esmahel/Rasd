<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->string('title', 255);
            $table->longText('content')->nullable();
            $table->longText('ai_draft_content')->nullable();
            $table->enum('generation_mode', ['ai', 'manual', 'hybrid'])->default('manual');
            $table->enum('status', ['draft', 'published'])->default('draft');
            $table->boolean('visible_to_monitors')->default(true);
            $table->date('report_date');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index('report_date');
            $table->index('status');
            $table->index('author_id');
            $table->index('visible_to_monitors');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
