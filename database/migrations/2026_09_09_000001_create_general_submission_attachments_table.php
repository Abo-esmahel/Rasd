<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('general_submission_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('general_submission_id')->constrained()->cascadeOnDelete();
            // Local disk relative path: submissions/{submission_id}/{uuid}.{ext}
            $table->string('file_path');
            $table->string('original_name');
            $table->string('mime_type');
            $table->unsignedBigInteger('file_size');
            $table->timestamps();

            $table->index('general_submission_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('general_submission_attachments');
    }
};
