<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('report_sheet_renders', function (Blueprint $table) {
            // FinalReportData كما اعتمدها المستخدم (observations + recommendations)
            // لإعادة تحميل المحرر بآخر بيانات مخصصة بدل فقدانها بعد التحديث.
            $table->json('payload')->nullable()->after('system_hash');
        });
    }

    public function down(): void
    {
        Schema::table('report_sheet_renders', function (Blueprint $table) {
            $table->dropColumn('payload');
        });
    }
};
