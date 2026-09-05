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

        // ——— المراقبون (6) ———
        User::create(['name' => 'طارق عبد الرحمن', 'username' => 'tariq', 'password' => $password, 'role' => 'monitor']);
        User::create(['name' => 'هادي السهلي',     'username' => 'hadi',  'password' => $password, 'role' => 'monitor']);
        User::create(['name' => 'حمزة الحاج قاسم', 'username' => 'hamza', 'password' => $password, 'role' => 'monitor']);
        User::create(['name' => 'رامي حموري',      'username' => 'rami',  'password' => $password, 'role' => 'monitor']);
        User::create(['name' => 'أنس الحسن',       'username' => 'anas',  'password' => $password, 'role' => 'monitor']);
        User::create(['name' => 'مازن خليل',       'username' => 'mazen', 'password' => $password, 'role' => 'monitor']);

        // ——— كتاب التقارير (2) ———
        User::create(['name' => 'زهير العبد الله', 'username' => 'writer',  'password' => $password, 'role' => 'report_writer']);
        User::create(['name' => 'فادي مراد',       'username' => 'writer2', 'password' => $password, 'role' => 'report_writer']);
    }
}
