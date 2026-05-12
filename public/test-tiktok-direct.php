<?php
// DELETE AFTER TESTING — test-tiktok-direct.php

$pixel_code   = 'D7U6LERC77U07JNLK5QG';
$access_token = 'PASTE_YOUR_TOKEN_HERE'; // paste raw token directly here

$payload = json_encode([
    'pixel_code' => $pixel_code,
    'event' => [[
        'event_name'  => 'SubmitForm',
        'event_id'    => 'test_direct_' . time(),
        'event_time'  => time(),
        'user'        => ['ip' => '1.2.3.4'],
        'page'        => ['url' => 'https://reg.dhakamodel.agency/'],
        'properties'  => ['currency' => 'BDT', 'value' => 0],
    ]]
]);

$ch = curl_init('https://business-api.tiktok.com/open_api/v1.3/event/track/');
curl_setopt($ch, CURLOPT_CUSTOMREQUEST,  'POST');
curl_setopt($ch, CURLOPT_POSTFIELDS,     $payload);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Access-Token: ' . $access_token,
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$response = curl_exec($ch);
$code     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "<pre>HTTP: $code\n\n" . json_encode(json_decode($response), JSON_PRETTY_PRINT) . "</pre>";