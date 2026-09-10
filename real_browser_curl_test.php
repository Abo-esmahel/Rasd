<?php
require 'vendor/autoload.php';
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;

$jar = new CookieJar();
$client = new Client(['base_uri' => 'http://127.0.0.1:8000']);

$res = $client->get('/login');
$body = (string)$res->getBody();
preg_match('/name="_token" value="([^"]+)"/', $body, $m);
$token = $m[1] ?? '';
preg_match('/name="csrf-token" content="([^"]+)"/', $body, $m2);
$csrf = $m2[1] ?? $token;
echo "GET /login token=".substr($token,0,10)."...\n";

$res = $client->post('/login', ['form_params' => ['username'=>'testuser','password'=>'password','_token'=>$token]]);
echo "POST /login status ".$res->getStatusCode()." location: ".implode(',', $res->getHeader('Location'))."\n";

$res = $client->get('/notes/create');
$body = (string)$res->getBody();
preg_match('/name="_token" value="([^"]+)"/', $body, $m);
$token2 = $m[1] ?? $csrf;
echo "GET /notes/create token=".substr($token2,0,10)."...\n";

$tmp = tempnam(sys_get_temp_dir(), 'real');
file_put_contents($tmp, hex2bin('89504e470d0a1a0a0000000d4948445200000001000000010802000000907753de0000000c4944415408d763f80f00000101000518d84f0000000049454e44ae426082'));
echo "Created tmp file $tmp size ".filesize($tmp)."\n";

$res = $client->post('/notes', [
    'headers' => [
        'X-Requested-With' => 'XMLHttpRequest',
        'Accept' => 'application/json',
        'X-CSRF-TOKEN' => $token2,
    ],
    'multipart' => [
        ['name'=>'_token','contents'=>$token2],
        ['name'=>'floor_number','contents'=>'1'],
        ['name'=>'camera_number','contents'=>'1'],
        ['name'=>'observed_at','contents'=>date('Y-m-d\TH:i')],
        ['name'=>'description','contents'=>'test via curl real browser simulation with image'],
        ['name'=>'files[]','contents'=>fopen($tmp,'r'),'filename'=>'test-real.jpg','headers'=>['Content-Type'=>'image/jpeg']],
        ['name'=>'action','contents'=>'save'],
    ]
]);
echo "POST /notes status ".$res->getStatusCode()."\n";
echo "Body: ".substr((string)$res->getBody(),0,1000)."\n";

$log = @file_get_contents('storage/logs/laravel.log');
$lines = array_filter(explode("\n",$log), fn($l)=>strpos($l,'[REAL TRACE]')!==false);
echo "\n=== LAST TRACE LOGS ===\n";
foreach(array_slice($lines,-20) as $l) echo substr($l,0,500)."\n";

@unlink($tmp);
