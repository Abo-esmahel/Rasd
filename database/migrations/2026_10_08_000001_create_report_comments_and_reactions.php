<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('report_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('report_comments')->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();
            $table->index(['report_id', 'created_at']);
        });

        Schema::create('reactions', function (Blueprint $table) {
            $table->id();
            $table->morphs('reactable');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('reaction', 16);
            $table->timestamps();
            $table->unique(['reactable_type', 'reactable_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reactions');
        Schema::dropIfExists('report_comments');
    }
};
