<?php
require 'vendor/autoload.php';
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;

$jar = new CookieJar();
$client = new Client(['base_uri' => 'http://127.0.0.1:8000', 'cookies' => $jar, 'http_errors' => false, 'allow_redirects' => false]);

// 1. GET login to get csrf
$res = $client->get('/login');
$body = (string)$res->getBody();
preg_match('/name="_token" value="([^"]+)"/', $body, $m);
$token = $m[1] ?? '';
preg_match('/name="csrf-token" content="([^"]+)"/', $body, $m2);
$csrf = $m2[1] ?? $token;
echo "GET /login token=".substr($token,0,10)."...\n";

// 2. POST login
$res = $client->post('/login', ['form_params' => ['username'=>'testuser','password'=>'password','_token'=>$token]]);
echo "POST /login status ".$res->getStatusCode()." location: ".implode(',', $res->getHeader('Location'))."\n";

// 3. GET /notes/create to get new token
$res = $client->get('/notes/create');
$body = (string)$res->getBody();
preg_match('/name="_token" value="([^"]+)"/', $body, $m);
$token2 = $m[1] ?? $csrf;
echo "GET /notes/create token=".substr($token2,0,10)."...\n";

// Create a real image file
$tmp = tempnam(sys_get_temp_dir(), 'real');
file_put_contents($tmp, hex2bin('89504e470d0a1a0a0000000d4948445200000001000000010802000000907753de0000000c4944415408d763f80f00000101000518d84f0000000049454e44ae426082'));
echo "Created tmp file $tmp size ".filesize($tmp)."\n";

// 4. POST /notes with multipart (simulate browser FormData with files[])
// The JS does fetch with headers X-Requested-With and X-CSRF-TOKEN, not using _token field? Actually it sends X-CSRF-TOKEN header and also _token in FormData via new FormData(formEl) which includes _token input
// For our test, we will send as the JS does: multipart with _token, floor_number etc, and files[]

// First try as the JS does: fetch with X-Requested-With and Accept json, body is FormData
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

// Also check logs
$log = @file_get_contents('storage/logs/laravel.log');
$lines = array_filter(explode("\n",$log), fn($l)=>strpos($l,'[REAL TRACE]')!==false);
echo "\n=== LAST TRACE LOGS ===\n";
foreach(array_slice($lines,-20) as $l) echo substr($l,0,500)."\n";

@unlink($tmp);
