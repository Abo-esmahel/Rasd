<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->integer('floor_number');
            $table->integer('camera_number');
            $table->dateTime('observed_at');
            $table->text('description');
            $table->enum('status', ['draft', 'pending', 'accepted', 'rejected'])->default('draft');
            $table->text('rejection_reason')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('observed_at');
            $table->index('floor_number');
            $table->index('camera_number');
            $table->index('processed_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notes');
    }
};
