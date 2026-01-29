<?php

use Illuminate\Support\Facades\Http;

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$masterSid = 'AC17dc2ce9f8313cba7ca874023bc75c18';
$masterToken = '8062a71bb72feef155551d5e6261585f';
$wabaId = '1452995289594243';
$number = 'whatsapp:+18392503849'; 

echo "Creating Main API Key...\n";
$keyResponse = Http::withBasicAuth($masterSid, $masterToken)
    ->asForm()
    ->post("https://api.twilio.com/2010-04-01/Accounts/{$masterSid}/Keys.json", [
        'FriendlyName' => 'Debug Temp Key',
    ]);

$keyData = $keyResponse->json();
$keySid = $keyData['sid'] ?? null;
$keySecret = $keyData['secret'] ?? null;

if (!$keySid) {
    echo "Failed to create key: " . $keyResponse->body() . "\n";
    exit;
}
echo "Key Created: $keySid\n";

echo "TEST 6: API Key Auth (Master Key)...\n";
// Using Key/Secret as credentials.
// This usually authenticates as the Account that owns the key (Master).
// But does it fix the permission? Likely no different from Master Auth.
// Unless we scope it?
// There's no way to 'scope' an API key creation to a subaccount in the Create Key params easily.
// But let's try just using it.
$response = Http::withBasicAuth($keySid, $keySecret)
    ->asJson()
    ->post('https://messaging.twilio.com/v2/Channels/Senders', [
         'sender_id' => $number,
         'profile' => ['name' => 'ApiKeyTest'],
         'configuration' => ['waba_id' => $wabaId],
    ]);
echo "Status: " . $response->status() . "\n";
echo "Body: " . $response->body() . "\n";

// Cleanup Key
Http::withBasicAuth($masterSid, $masterToken)
    ->delete("https://api.twilio.com/2010-04-01/Accounts/{$masterSid}/Keys/{$keySid}.json");
echo "Key Deleted.\n";
