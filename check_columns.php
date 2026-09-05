<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$cols = Illuminate\Support\Facades\DB::select("PRAGMA table_info(users)");
foreach($cols as $c){ echo $c->name . " | " . $c->type . "\n"; }
echo "---\n";
$rows = Illuminate\Support\Facades\DB::select("SELECT sql FROM sqlite_master WHERE type='table' AND name='users'");
echo $rows[0]->sql . "\n";
