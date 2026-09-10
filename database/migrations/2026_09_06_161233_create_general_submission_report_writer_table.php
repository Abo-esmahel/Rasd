<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    
    public function up(): void
    {
        Schema::create('general_submission_report_writer', function (Blueprint $table) {
            $table->id();
            $table->foreignId('general_submission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unique(['general_submission_id', 'user_id']);
            $table->timestamps();
        });
    }

    
    public function down(): void
    {
        Schema::dropIfExists('general_submission_report_writer');
    }
};
