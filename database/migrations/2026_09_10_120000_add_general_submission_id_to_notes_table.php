<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notes', function (Blueprint $table) {
            $table->unsignedBigInteger('general_submission_id')->nullable()->after('user_id');
            $table->index('general_submission_id', 'notes_general_submission_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('notes', function (Blueprint $table) {
            $table->dropIndex('notes_general_submission_id_index');
            $table->dropColumn('general_submission_id');
        });
    }
};
