<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/vendor/autoload.php';

$spreadsheetId = SPREADSHEET_ID;

define('SCOPES', implode(' ', array(
        Google_Service_Sheets::SPREADSHEETS)
));

if (php_sapi_name() != 'cli') {
    throw new Exception('This application must be run on the command line.');
}

/**
 * Returns an authorized API client.
 * @return Google_Client the authorized client object
 */
function getClient() {
    $client = new Google_Client();
    $client->setApplicationName(APPLICATION_NAME);
    $client->setScopes(SCOPES);
    $client->setAuthConfig(CLIENT_SECRET_PATH);
    $client->setAccessType('offline');

    // Load previously authorized credentials from a file.
    $credentialsPath = expandHomeDirectory(CREDENTIALS_PATH);
    if (file_exists($credentialsPath)) {
        $accessToken = json_decode(file_get_contents($credentialsPath), true);
    } else {
        // Request authorization from the user.
        $authUrl = $client->createAuthUrl();
        printf("Open the following link in your browser:\n%s\n", $authUrl);
        print 'Enter verification code: ';
        $authCode = trim(fgets(STDIN));

        // Exchange authorization code for an access token.
        $accessToken = $client->fetchAccessTokenWithAuthCode($authCode);

        // Store the credentials to disk.
        if(!file_exists(dirname($credentialsPath))) {
            mkdir(dirname($credentialsPath), 0700, true);
        }
        file_put_contents($credentialsPath, json_encode($accessToken));
        printf("Credentials saved to %s\n", $credentialsPath);
    }
    $client->setAccessToken($accessToken);

    // Refresh the token if it's expired.
    if ($client->isAccessTokenExpired()) {
        $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
        file_put_contents($credentialsPath, json_encode($client->getAccessToken()));
    }
    return $client;
}

/**
 * Expands the home directory alias '~' to the full path.
 * @param string $path the path to expand.
 * @return string the expanded path.
 */
function expandHomeDirectory($path) {
    $homeDirectory = getenv('HOME');
    if (empty($homeDirectory)) {
        $homeDirectory = getenv('HOMEDRIVE') . getenv('HOMEPATH');
    }
    return str_replace('~', realpath($homeDirectory), $path);
}

// Get the API client and construct the service object.
$client = getClient();

$service = new Google_Service_Sheets($client);

$range = 'Data 1!A1:B';

$result = $service->spreadsheets_values->get($spreadsheetId, $range);
$rows = $result->getValues() != null ? count($result->getValues()) : 0;

//Clear all past values
$clear_values = [];
for($i=0;$i<$rows;$i++){
    $clear_values[]=['', ''];
}
$data = [];
$data[] = new Google_Service_Sheets_ValueRange([
    'range' => $range,
    'values' => $clear_values
]);
$body = new Google_Service_Sheets_BatchUpdateValuesRequest([
    'valueInputOption' => 'RAW',
    'data' => $data
]);
$result = $service->spreadsheets_values->batchUpdate($spreadsheetId, $body);

//Write new values
$range = 'Data 1!A1:B';
$values = [];

$f = @fopen("data.json", "r") or die("Nothing to read yet".PHP_EOL);;
$obj = json_decode(fread($f,filesize("data.json")));
fclose($f);

foreach ($obj as $org => $value){
    $values[] = [$org, $value];
}

$data = [];
$data[] = new Google_Service_Sheets_ValueRange([
    'range' => $range,
    'values' => $values
]);
$body = new Google_Service_Sheets_BatchUpdateValuesRequest([
    'valueInputOption' => 'RAW',
    'data' => $data
]);
$result = $service->spreadsheets_values->batchUpdate($spreadsheetId, $body);
printf("%d cells updated.", $result->getTotalUpdatedCells());