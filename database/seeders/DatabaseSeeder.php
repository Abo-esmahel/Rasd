<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\Localization\NameTransliterationService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $password = Hash::make('password');

        $users = [
            ['name' => 'طارق عبد الرحمن', 'name_en' => 'Tariq Abdulrahman', 'name_ar' => 'طارق عبد الرحمن', 'username' => 'tariq', 'role' => 'monitor', 'personal_number' => '0933124578'],
            ['name' => 'هيثم الدبس', 'name_en' => 'Haitham Al Dabs', 'name_ar' => 'هيثم الدبس', 'username' => 'hadi', 'role' => 'monitor', 'personal_number' => '0944783216'],
            ['name' => 'خالد الاصفهاني', 'name_en' => 'Khaled Al Isfahani', 'name_ar' => 'خالد الاصفهاني', 'username' => 'hamza', 'role' => 'monitor', 'personal_number' => '0955319847'],
            ['name' => 'مأمون الكعكي', 'name_en' => 'Mamoun Al Kaaki', 'name_ar' => 'مأمون الكعكي', 'username' => 'rami', 'role' => 'monitor', 'personal_number' => '0933658421'],
            ['name' => 'شادي النداف', 'name_en' => 'Shadi Al Naddaf', 'name_ar' => 'شادي النداف', 'username' => 'writer', 'role' => 'report_writer', 'personal_number' => '0962471583'],
        ];

        foreach ($users as $u) {
            $user = User::firstOrCreate(['username' => $u['username']], $u + ['password' => $password]);
            if (!$user->wasRecentlyCreated && ($user->role !== $u['role'] || $user->name !== $u['name'])) {
                $user->update(['name' => $u['name'], 'role' => $u['role']]);
            }
            if (empty($user->name_en) || empty($user->name_ar)) {
                $translit = app(NameTransliterationService::class);
                $nameEn = $u['name_en'] ?? $translit->generateLatinName($u['name']);
                $nameAr = $u['name_ar'] ?? $u['name'];
                $user->updateQuietly(['name_en' => $nameEn, 'name_ar' => $nameAr]);
            }
            if (!empty($u['personal_number']) && $user->personal_number !== $u['personal_number']) {
                $user->updateQuietly(['personal_number' => $u['personal_number']]);
            }
        }
    }
}
