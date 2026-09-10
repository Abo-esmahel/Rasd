<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('sound_enabled')->default(true);
            $table->boolean('desktop_enabled')->default(true);
            $table->integer('volume')->default(70); 
            $table->boolean('toast_enabled')->default(true);
            $table->string('sound_theme')->default('default'); 
            $table->json('muted_types')->nullable(); 
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
    }
};
