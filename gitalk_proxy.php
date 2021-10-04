<?php
require 'proxy.php';
if($_SERVER['REQUEST_METHOD'] != 'POST') {
    die();
}
$requestBody = file_get_contents('php://input');
$httpArgs = array(
    'header'  => "Content-type: application/json\r\n",
    'method'  => 'POST',
    'content' => $requestBody
);
if(isset($proxy)) {
    $httpArgs['proxy'] = $proxy;
}
$options = array(
    'http' => $httpArgs
);
$result = file_get_contents("https://github.com/login/oauth/access_token", false, stream_context_create($options));
if ($result === FALSE) {
    http_response_code(504);
    die();
}
http_response_code(200);
die($result);
?>
