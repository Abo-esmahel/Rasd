<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicateDay = DB::table('reports')
            ->select('report_date')
            ->groupBy('report_date')
            ->havingRaw('COUNT(*) > 1')
            ->value('report_date');

        if ($duplicateDay !== null) {
            throw new RuntimeException("Cannot enforce one report per day: multiple reports already exist for {$duplicateDay}. Resolve those reports before rerunning this migration.");
        }

        $duplicateNoteId = DB::table('report_note')
            ->select('note_id')
            ->groupBy('note_id')
            ->havingRaw('COUNT(*) > 1')
            ->value('note_id');

        if ($duplicateNoteId !== null) {
            throw new RuntimeException("Cannot enforce one report per note: note #{$duplicateNoteId} is linked to multiple reports. Resolve that link before rerunning this migration.");
        }

        Schema::table('reports', function (Blueprint $table) {
            $table->unique('report_date', 'reports_report_date_unique');
        });

        Schema::table('report_note', function (Blueprint $table) {
            $table->unique('note_id', 'report_note_note_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('report_note', function (Blueprint $table) {
            $table->dropUnique('report_note_note_id_unique');
        });

        Schema::table('reports', function (Blueprint $table) {
            $table->dropUnique('reports_report_date_unique');
        });
    }
};
