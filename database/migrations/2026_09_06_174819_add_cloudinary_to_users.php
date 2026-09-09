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
        Schema::table('users', function (Blueprint $table) {
            $table->string('avatar_public_id')->nullable()->after('avatar_path');
            $table->string('avatar_resource_type')->nullable()->after('avatar_public_id');
            $table->text('avatar_secure_url')->nullable()->after('avatar_resource_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['avatar_public_id', 'avatar_resource_type', 'avatar_secure_url']);
        });
    }
};
