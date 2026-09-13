<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_sheet_renders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained('reports')->cascadeOnDelete();
            $table->unsignedInteger('generation_no');
            $table->unsignedInteger('data_version')->default(1);
            $table->string('template', 20);
            $table->string('payload_hash', 64);
            $table->string('system_hash', 64)->nullable();
            $table->string('image_path', 255);
            $table->timestamps();

            $table->unique(['report_id', 'payload_hash']);
            $table->index(['report_id', 'generation_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_sheet_renders');
    }
};
