<?php
if($_SERVER['REQUEST_METHOD'] != 'POST') {
    die();
}
$requestBody = file_get_contents('php://input');
$options = array(
    'http' => array(
        'header'  => "Content-type: application/json\r\n",
        'method'  => 'POST',
        'content' => $requestBody
    )
);
$context  = stream_context_create($options);
$result = file_get_contents("https://github.com/login/oauth/access_token", false, $context);
if ($result === FALSE) {
    http_response_code(504);
    die();
}
http_response_code(200);
die($result);
?>
