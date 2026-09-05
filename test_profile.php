<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

$user = User::first();
if(!$user){
    echo "No user\n";
    exit;
}
echo "User: {$user->id} - {$user->name} - {$user->username} - personal_number: " . ($user->personal_number ?? 'null') . "\n";
echo "Columns: " . implode(', ', Illuminate\Support\Facades\Schema::getColumnListing('users')) . "\n";

// Try to update personal_number
try {
    $user->personal_number = '12345678901';
    $user->save();
    echo "Update with personal_number succeeded\n";
    echo "New value: " . $user->fresh()->personal_number . "\n";
    // revert
    $user->personal_number = null;
    $user->save();
    echo "Reverted\n";
} catch (Exception $e) {
    echo "Update failed: " . $e->getMessage() . "\n";
}

// Try to render profile view
try {
    $view = view('profile.show', ['user' => $user])->render();
    echo "View render succeeded, length: " . strlen($view) . "\n";
} catch (Exception $e) {
    echo "View render failed: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
