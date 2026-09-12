<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_note', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained('reports')->cascadeOnDelete();
            $table->foreignId('note_id')->constrained('notes')->cascadeOnDelete();
            $table->integer('order_index')->default(0);
            $table->timestamps();

            $table->unique(['report_id', 'note_id']);
            $table->index('report_id');
            $table->index('note_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_note');
    }
};
