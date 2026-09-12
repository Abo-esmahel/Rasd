<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('general_submissions', function (Blueprint $table) {
            $table->index('user_id');
            $table->index('status');
            $table->index('observed_at');
            $table->index('processed_by');
            $table->index(['status', 'observed_at']);
            $table->index(['user_id', 'status']);
        });

        Schema::table('push_subscriptions', function (Blueprint $table) {
            $table->index('user_id');
        });

        Schema::table('general_submission_report_writer', function (Blueprint $table) {
            $table->index('user_id');
        });

        Schema::table('report_revisions', function (Blueprint $table) {
            $table->index('editor_id');
        });
    }

    public function down(): void
    {
        Schema::table('general_submissions', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
            $table->dropIndex(['status']);
            $table->dropIndex(['observed_at']);
            $table->dropIndex(['processed_by']);
            $table->dropIndex(['status', 'observed_at']);
            $table->dropIndex(['user_id', 'status']);
        });

        Schema::table('push_subscriptions', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
        });

        Schema::table('general_submission_report_writer', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
        });

        Schema::table('report_revisions', function (Blueprint $table) {
            $table->dropIndex(['editor_id']);
        });
    }
};
