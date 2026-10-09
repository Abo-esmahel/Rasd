<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('monitor', 'report_writer', 'general_manager') NOT NULL");
            return;
        }

        if ($driver === 'sqlite') {
            try {
                $pdo = DB::connection()->getPdo();
                $sql = $pdo->query("SELECT sql FROM sqlite_master WHERE type='table' AND name='users'")->fetchColumn();
                if ($sql && str_contains($sql, 'general_manager')) {
                    return;
                }
                $pdo->exec("CREATE TABLE \"users_new\" (
                    \"id\" integer primary key autoincrement not null,
                    \"name\" varchar not null,
                    \"username\" varchar not null,
                    \"password\" varchar not null,
                    \"role\" varchar check (\"role\" in ('monitor', 'report_writer', 'general_manager')) not null,
                    \"created_at\" datetime,
                    \"updated_at\" datetime,
                    \"avatar_path\" varchar,
                    \"personal_number\" varchar,
                    \"avatar_public_id\" varchar,
                    \"avatar_resource_type\" varchar,
                    \"avatar_secure_url\" text,
                    \"remember_token\" varchar,
                    \"locale\" varchar not null default 'ar',
                    \"name_en\" varchar,
                    \"name_ar\" varchar
                )");
                $pdo->exec("INSERT INTO \"users_new\" SELECT * FROM \"users\"");
                $pdo->exec("DROP TABLE \"users\"");
                $pdo->exec("ALTER TABLE \"users_new\" RENAME TO \"users\"");
                try { $pdo->exec("CREATE UNIQUE INDEX \"users_username_unique\" on \"users\" (\"username\")"); } catch (\Throwable $e) {}
                try { $pdo->exec("CREATE UNIQUE INDEX \"users_personal_number_unique\" on \"users\" (\"personal_number\")"); } catch (\Throwable $e) {}
                try { $pdo->exec("CREATE INDEX \"users_role_index\" on \"users\" (\"role\")"); } catch (\Throwable $e) {}
                try { $pdo->exec("CREATE INDEX \"users_remember_token_index\" on \"users\" (\"remember_token\")"); } catch (\Throwable $e) {}
            } catch (\Throwable $e) {
            }
            return;
        }

        if (Schema::hasColumn('users', 'role')) {
            try {
                Schema::table('users', function (Blueprint $table) {
                    $table->string('role')->default('monitor')->change();
                });
            } catch (\Throwable $e) {
            }
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            try {
                DB::table('users')->where('role', 'general_manager')->update(['role' => 'monitor']);
            } catch (\Throwable $e) {}
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('monitor', 'report_writer') NOT NULL");
            return;
        }

        if ($driver === 'sqlite') {
            try {
                DB::table('users')->where('role', 'general_manager')->update(['role' => 'monitor']);
                $pdo = DB::connection()->getPdo();
                $sql = $pdo->query("SELECT sql FROM sqlite_master WHERE type='table' AND name='users'")->fetchColumn();
                if ($sql && str_contains($sql, 'general_manager')) {
                    $pdo->exec("CREATE TABLE \"users_new\" (
                        \"id\" integer primary key autoincrement not null,
                        \"name\" varchar not null,
                        \"username\" varchar not null,
                        \"password\" varchar not null,
                        \"role\" varchar check (\"role\" in ('monitor', 'report_writer')) not null,
                        \"created_at\" datetime,
                        \"updated_at\" datetime,
                        \"avatar_path\" varchar,
                        \"personal_number\" varchar,
                        \"avatar_public_id\" varchar,
                        \"avatar_resource_type\" varchar,
                        \"avatar_secure_url\" text,
                        \"remember_token\" varchar,
                        \"locale\" varchar not null default 'ar',
                        \"name_en\" varchar,
                        \"name_ar\" varchar
                    )");
                    $pdo->exec("INSERT INTO \"users_new\" SELECT * FROM \"users\"");
                    $pdo->exec("DROP TABLE \"users\"");
                    $pdo->exec("ALTER TABLE \"users_new\" RENAME TO \"users\"");
                    try { $pdo->exec("CREATE UNIQUE INDEX \"users_username_unique\" on \"users\" (\"username\")"); } catch (\Throwable $e) {}
                    try { $pdo->exec("CREATE UNIQUE INDEX \"users_personal_number_unique\" on \"users\" (\"personal_number\")"); } catch (\Throwable $e) {}
                    try { $pdo->exec("CREATE INDEX \"users_role_index\" on \"users\" (\"role\")"); } catch (\Throwable $e) {}
                    try { $pdo->exec("CREATE INDEX \"users_remember_token_index\" on \"users\" (\"remember_token\")"); } catch (\Throwable $e) {}
                }
            } catch (\Throwable $e) {}
            return;
        }

        if (Schema::hasColumn('users', 'role')) {
            try {
                DB::table('users')->where('role', 'general_manager')->update(['role' => 'monitor']);
            } catch (\Throwable $e) {}
        }
    }
};
