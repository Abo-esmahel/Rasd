<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_sheet_renders', function (Blueprint $table) {
            $table->id();
            // رقم الجيل المتسلسل لكل تقرير (لمنع Race: الأحدث generation_no هو المعتمد)
            $table->foreignId('report_id')->constrained('reports')->cascadeOnDelete();
            $table->unsignedInteger('generation_no');
            // نسخة البيانات القادمة من العميل (DATA VERSION) — تُعاد كما هي لكشف النتائج القديمة
            $table->unsignedInteger('data_version')->default(1);
            // القالب الحتمي المختار من السيرفر (template-1 .. template-7)
            $table->string('template', 20);
            // بصمة الـRender Payload — لإعادة استخدام صورة سابقة بدل Gemini عند تطابق البيانات
            $table->string('payload_hash', 64);
            // بصمة بيانات النظام لحظة التوليد — لكشف قِدم الصورة المعبأة من بيانات مخصصة
            $table->string('system_hash', 64)->nullable();
            $table->string('image_path', 255);
            $table->timestamps();

            $table->unique(['report_id', 'payload_hash']);
            $table->index(['report_id', 'generation_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_sheet_renders');
    }
};
