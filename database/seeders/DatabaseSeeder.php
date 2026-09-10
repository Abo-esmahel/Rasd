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


        User::create(['name' => 'طارق عبد الرحمن', 'username' => 'tariq', 'password' => $password, 'role' => 'monitor']);
        User::create(['name' => 'هادي سهلي',     'username' => 'hadi',  'password' => $password, 'role' => 'monitor']);
        User::create(['name' => 'حمزة الحاج قاسم', 'username' => 'hamza', 'password' => $password, 'role' => 'monitor']);
        User::create(['name' => 'رامي حموري',      'username' => 'rami',  'password' => $password, 'role' => 'monitor']);


        User::create(['name' => 'زهير العبد الله', 'username' => 'writer',  'password' => $password, 'role' => 'report_writer']);
    }
}
