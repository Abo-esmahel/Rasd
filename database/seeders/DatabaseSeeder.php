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

        $users = [
            ['name' => 'طارق عبد الرحمن', 'username' => 'tariq', 'role' => 'monitor'],
            ['name' => 'هادي سهلي', 'username' => 'hadi', 'role' => 'monitor'],
            ['name' => 'حمزة الحاج قاسم', 'username' => 'hamza', 'role' => 'monitor'],
            ['name' => 'رامي حموري', 'username' => 'rami', 'role' => 'monitor'],
            ['name' => 'زهير العبد الله', 'username' => 'writer', 'role' => 'report_writer'],
        ];

        foreach ($users as $u) {
            $user = User::firstOrCreate(['username' => $u['username']], $u + ['password' => $password]);
            if (!$user->wasRecentlyCreated && ($user->role !== $u['role'] || $user->name !== $u['name'])) {
                $user->update(['name' => $u['name'], 'role' => $u['role']]);
            }
        }
    }
}
