<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained('reports')->cascadeOnDelete();
            $table->foreignId('editor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->longText('content_snapshot');
            $table->timestamps();

            $table->index('report_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_revisions');
    }
};
