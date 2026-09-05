<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$users = App\Models\User::all();
foreach($users as $u){
    echo "ID {$u->id} | {$u->name} | {$u->username} | avatar_path=" . var_export($u->avatar_path, true) . " | avatar_url=" . var_export($u->avatar_url, true) . " | personal=" . var_export($u->personal_number, true) . "\n";
    if($u->avatar_path){
        $exists = Illuminate\Support\Facades\Storage::disk('public')->exists($u->avatar_path) ? 'exists' : 'missing';
        echo "  storage exists: $exists\n";
    }
}
