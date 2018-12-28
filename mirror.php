<?php
require 'config.php';
$authToken = null;
//1. fetch 

if ($argc == 2) {
    $app = $argv[1];//e.g. "com.kainy.ouya"
    logDebug('Mirroring app: ' . $app);
    mirrorApp('ouya://launcher/details?app=' . $app);

} else {
    logDebug('Mirroring full "discover" section');
    mirrorDiscover();
}


function mirrorDiscover()
{
    $json = mirrorJson('https://devs.ouya.tv/api/v1/discover');
    $data = json_decode($json);
    assertNoError($data);
    foreach ($data->tiles as $tile) {
        mirrorImage($tile->image, true);
        if (isset($tile->heroImage)) {
            mirrorImage($tile->heroImage, true);
        }
        if (isset($tile->mobileAppIcon)) {
            mirrorImage($tile->mobileAppIcon, true);
        }
        mirrorApp($tile->url);
    }
}

function mirrorApp($url)
{
    parse_str(parse_url($url, PHP_URL_QUERY), $queryParams);
    if (!isset($queryParams['app'])) {
        return;
    }
    $packageName = $queryParams['app'];
    $json = mirrorJson('https://devs.ouya.tv/api/v1/apps/' . $packageName);
    $data = json_decode($json);
    if (isset($data->app->mobileAppIcon)) {
        mirrorImage($data->app->mobileAppIcon, true);
    }
    mirrorImage($data->app->mainImageFullUrl, true);
    foreach ($data->app->filepickerScreenshots as $imgUrl) {
        mirrorImage($imgUrl, true);
    }

    if ($data->app->premium) {
        logDebug('No download for ' . $url);
        return;
    }

    $json = mirrorJson('https://devs.ouya.tv/api/v1/apps/' . $packageName . '/download');
    $data = json_decode($json);
    mirror($data->app->downloadLink);
}

function mirrorImage($url, $allowFail = false)
{
    return mirror($url, $allowFail);
}

function mirrorJson($url)
{
    return mirror($url, false, '.json');
}

function mirror($url, $allowFail = false, $suffix = '')
{
    global $authToken, $username, $password;

    if ($url == '') {
        return;
    }

    $schemelessUrl = str_replace(array('http://', 'https://'), '', $url);
    $file = __DIR__ . '/data/' . $schemelessUrl . $suffix;
    if (file_exists($file)) {
        logDebug('URL already mirrored: ' . $url);
        return file_get_contents($file);
    }

    if ($authToken === null) {
        $authToken = getAuthToken($username, $password);
    }

    $res = fetch($url, $authToken, $allowFail);
    if ($res === false && $allowFail) {
        return false;
    }

    $fileDir = __DIR__ . '/data/' . dirname($schemelessUrl);
    if (!is_dir($fileDir)) {
        $ok = mkdir($fileDir, 0777, true);
        if (!$ok) {
            failHard('Cannot create directory: ' . $fileDir);
        }
    }
    file_put_contents($file, $res);
    return $res;
}

function failHard($msg)
{
    file_put_contents('php://stderr', $msg . "\n");
    exit(1);
}
function getAuthToken($username, $password)
{
    $ctx = stream_context_create(
        array(
            'http' => array(
                'method' => 'POST',
                'ignore_errors' => true,
                'header' => array(
                    'Content-Type: application/x-www-form-urlencoded',
                    //'User-Agent: OUYA 0 1.00 1.2.1427_r1',
                    'X-OUYA-Console-Id: 0123456789012345',
                    //'X-OUYA-Device: ouya_1_1'
                ),
                'content' => http_build_query(
                    array(
                        'username' => $username,
                        'password' => $password
                    )
                )
            )
        )
    );
    $res = file_get_contents(
        'https://devs.ouya.tv/api/v1/sessions', false, $ctx
    );
    if ($res === false) {
        failHard('Error logging in');
    }
    $data = json_decode($res);
    assertNoError($data);
    logDebug('Auth token: ' . $data->token);
    return $data->token;
}

function fetch($url, $authToken, $allowFail = false)
{
    $header = array();
    if (parse_url($url, PHP_URL_HOST) == 'devs.ouya.tv') {
        $header = array(
            //'User-Agent: OUYA 0 1.00 1.2.1427_r1',
            'X-OUYA-Console-Id: 0123456789012345',
            'X-OUYA-AuthToken: ' . $authToken,
            //'X-OUYA-Device: ouya_1_1'
        );
    }
    $ctx = stream_context_create(
        array(
            'http' => array(
                'ignore_errors' => true,
                'header'        => $header,
            )
        )
    );
    logDebug('Fetching URL ' . $url);
    $res = file_get_contents($url, false, $ctx);
    if ($res === false) {
        failHard('Error logging in');
    }
    list($http, $code, $title) = explode(' ', $http_response_header[0], 3);
    if ($code != 200) {
        $msg = 'Error fetching URL ' . $url . "\n"
            . ' HTTP code ' . $code
            . ', title: ' . $title;

        if ($allowFail) {
            logDebug($msg);
            return false;
        }
        failhard($msg);
    }
    return $res;
}

function fetchData($url, $authToken)
{
    $res = fetch($url, $authToken);
    $data = json_decode($res);
    assertNoError($data);
    return $data;
}

function assertNoError($data)
{
    if (!isset($data->error)) {
        return;
    }
    failHard(
        'Error in JSON: ' . $data->error->code . ': ' . $data->error->message
    );
}

function logDebug($msg)
{
    echo $msg . "\n";
}
?>
