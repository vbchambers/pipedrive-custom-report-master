<?php
require_once __DIR__ . '/config.php';

// Pipedrive API token
$api_token = PIPEDRIVE_API_TOKEN;

$base_url = PIPEDRIVE_DOMAIN.'/v1/deals';
$url_params = array(
    'status' => 'won',
    'api_token' => $api_token
);

$url = $base_url.'?'.http_build_query($url_params);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

echo 'Updating...' . PHP_EOL;

$output = curl_exec($ch);
curl_close($ch);

$result = json_decode($output, true);

$values_per_org = [];

if(!empty($result['data'])) {
    foreach ($result['data'] as $key => $deal) {
        $org_name = $deal['org_id']['name'];
        $values_per_org[$org_name] = !empty($values_per_org[$org_name]) ? $values_per_org[$org_name] + $deal['value'] : $deal['value'];
    }
}

$f = fopen('data.json', 'w');
fwrite($f, json_encode($values_per_org));
fclose($f);

echo 'File updated' . PHP_EOL;

exec('php '.__DIR__.DIRECTORY_SEPARATOR.'update_gspreadsheet.php');