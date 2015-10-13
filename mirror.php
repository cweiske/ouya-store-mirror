<?php
require 'config.php';
$authToken = null;
//1. fetch 

mirror('api/v1/discover');


function mirror($path)
{
    global $authToken;

    $file = __DIR__ . '/data/' . $path;
    if (file_exists($file)) {
        return file_get_contents($file);
    }

    if ($authToken === null) {
        $authToken = getAuthToken($username, $password);
    }

    $data = fetch('https://devs.ouya.tv/' . $path, $authToken);
    $filePath = __DIR__ . '/data/' . dirname($path);
    mkdir($filePath, 0777, true);
    file_put_contents($file, $data);
    return $data;
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
    if (isset($data->error)) {
        failHard('Error logging in: ' . $data->error->message);
    }
    return $data->token;
}

function fetch($url, $authToken)
{
    $ctx = stream_context_create(
        array(
            'http' => array(
                'ignore_errors' => true,
                'header' => array(
                    //'User-Agent: OUYA 0 1.00 1.2.1427_r1',
                    'X-OUYA-Console-Id: 0123456789012345',
                    'X-OUYA-AuthToken: ' . $authToken,
                    //'X-OUYA-Device: ouya_1_1'
                )
            )
        )
    );
    $res = file_get_contents($url, false, $ctx);
    if ($res === false) {
        failHard('Error logging in');
    }
    return $res;
}

function fetchData($url, $authToken)
{
    $res = fetch($url, $authToken);
    $data = json_decode($res);
    if (isset($data->error)) {
        failHard('Error fetching URL: ' . $data->error->message);
    }
    return $data;
}

?>