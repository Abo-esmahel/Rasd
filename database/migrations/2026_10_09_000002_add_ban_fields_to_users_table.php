<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * حظر الحساب — للمدير العام فقط.
 *
 * الحظر لا يحذف المستخدم ولا يمسّ ملاحظاته: هو قفل لتسجيل الدخول فقط،
 * مع إبقاء كل ما سجّله سابقاً كما هو. سبب الحظر يُحفظ ليُعرض للمدير العام
 * عند رفع الحظر، فهو يفسّر قرار الرفع.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'banned_at')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('banned_at')->nullable()->after('role');
            $table->text('ban_reason')->nullable()->after('banned_at');
            $table->index('banned_at');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'banned_at')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['banned_at']);
            $table->dropColumn(['banned_at', 'ban_reason']);
        });
    }
};
