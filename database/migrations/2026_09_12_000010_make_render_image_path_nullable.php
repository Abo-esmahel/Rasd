<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('report_sheet_renders', function (Blueprint $table) {
            // مسار HTML: لا PNG بعد الآن — image_path يبقى للتوافق فقط (nullable).
            $table->string('image_path', 255)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('report_sheet_renders', function (Blueprint $table) {
            $table->string('image_path', 255)->nullable(false)->change();
        });
    }
};
