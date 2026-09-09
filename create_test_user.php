<?php
require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$kernel=$app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$u=App\Models\User::where('username','testuser')->first();
if(!$u){
  $u=App\Models\User::create(['name'=>'Test','username'=>'testuser','password'=>bcrypt('password'),'role'=>'monitor']);
  echo "created ".$u->id."\n";
} else {
  $u->password=bcrypt('password');
  $u->save();
  echo "updated ".$u->id."\n";
}
