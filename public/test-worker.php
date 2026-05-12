<?php
// DELETE THIS FILE AFTER TESTING

$payload = [
    'event_name' => 'TestEvent',
    'event_id'   => 'test_' . time(),
    'page_url'   => 'https://reg.dhakamodel.agency/test',
    'user_data'  => ['ip' => '1.2.3.4'],
    'custom_data'=> ['currency' => 'BDT', 'value' => 0],
];

$ch = curl_init('https://dma-capi-proxy.admin-dhakamodelagency.workers.dev');
curl_setopt($ch, CURLOPT_CUSTOMREQUEST,  'POST');
curl_setopt($ch, CURLOPT_POSTFIELDS,     json_encode($payload));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER,    ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_TIMEOUT_MS,     5000);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_VERBOSE,        true);

$response  = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_err  = curl_error($ch);
curl_close($ch);

echo "<pre>";
echo "HTTP Code: $http_code\n";
echo "cURL Error: " . ($curl_err ?: 'none') . "\n";
echo "Response: $response\n";
echo "</pre>";