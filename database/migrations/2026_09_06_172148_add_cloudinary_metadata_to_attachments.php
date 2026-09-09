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
        Schema::table('attachments', function (Blueprint $table) {
            $table->string('cloudinary_resource_type')->nullable()->after('file_path');
            $table->string('cloudinary_format')->nullable()->after('cloudinary_resource_type');
            $table->text('secure_url')->nullable()->after('cloudinary_format');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attachments', function (Blueprint $table) {
            $table->dropColumn(['cloudinary_resource_type', 'cloudinary_format', 'secure_url']);
        });
    }
};
