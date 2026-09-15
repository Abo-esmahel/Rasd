<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attachments', function (Blueprint $table) {
            $table->index('created_at');
            $table->index('mime_type');
        });

        Schema::table('general_submission_attachments', function (Blueprint $table) {
            $table->index('created_at');
            $table->index('mime_type');
        });

        Schema::table('notes', function (Blueprint $table) {
            $table->index('created_at');
            $table->index(['status', 'created_at']);
        });

        Schema::table('general_submissions', function (Blueprint $table) {
            $table->index('created_at');
            $table->index(['status', 'created_at']);
        });

        Schema::table('reports', function (Blueprint $table) {
            $table->index(['status', 'report_date']);
        });
    }

    public function down(): void
    {
        Schema::table('attachments', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
            $table->dropIndex(['mime_type']);
        });

        Schema::table('general_submission_attachments', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
            $table->dropIndex(['mime_type']);
        });

        Schema::table('notes', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
            $table->dropIndex(['status', 'created_at']);
        });

        Schema::table('general_submissions', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
            $table->dropIndex(['status', 'created_at']);
        });

        Schema::table('reports', function (Blueprint $table) {
            $table->dropIndex(['status', 'report_date']);
        });
    }
};
