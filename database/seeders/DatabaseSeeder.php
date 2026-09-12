<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $password = Hash::make('password');

        // آمن للتكرار: التشغيل الثاني كان ينفجر بـ UNIQUE constraint على username.
        $users = [
            ['name' => 'طارق عبد الرحمن', 'username' => 'tariq', 'role' => 'monitor'],
            ['name' => 'هادي سهلي', 'username' => 'hadi', 'role' => 'monitor'],
            ['name' => 'حمزة الحاج قاسم', 'username' => 'hamza', 'role' => 'monitor'],
            ['name' => 'رامي حموري', 'username' => 'rami', 'role' => 'monitor'],
            ['name' => 'زهير العبد الله', 'username' => 'writer', 'role' => 'report_writer'],
        ];

        foreach ($users as $u) {
            User::firstOrCreate(['username' => $u['username']], $u + ['password' => $password]);
        }
    }
}
