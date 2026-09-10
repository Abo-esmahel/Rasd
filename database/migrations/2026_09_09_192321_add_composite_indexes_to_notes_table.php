<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('notes', function (Blueprint $table) {
            $table->index(['user_id', 'status'], 'notes_user_id_status_index');
            $table->index(['status', 'observed_at'], 'notes_status_observed_at_index');
            $table->index(['user_id', 'observed_at'], 'notes_user_id_observed_at_index');
            $table->index(['floor_number', 'camera_number', 'observed_at'], 'notes_floor_camera_observed_index');
            $table->index(['user_id', 'created_at'], 'notes_user_id_created_at_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notes', function (Blueprint $table) {
            $table->dropIndex('notes_user_id_status_index');
            $table->dropIndex('notes_status_observed_at_index');
            $table->dropIndex('notes_user_id_observed_at_index');
            $table->dropIndex('notes_floor_camera_observed_index');
            $table->dropIndex('notes_user_id_created_at_index');
        });
    }
};
