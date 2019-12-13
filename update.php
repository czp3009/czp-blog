<?php
require 'hook_config.php';
function response($code = 200, $message = null) {
    http_response_code($code);
    die($message);
}
function forbidden() {
    response(403);
}
function badRequest() {
    response(400);
}
//验证 UA
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
if ($userAgent === null || strpos($userAgent, 'GitHub-Hookshot') !== 0) {
    forbidden();
}
//验证密钥
$sign = $_SERVER['HTTP_X_HUB_SIGNATURE'] ?? null;
if ($sign === null) {
    forbidden();
}
list($algo, $hash) = explode('=', $sign, 2) + array('', '');
if (!in_array($algo, hash_algos(), TRUE)) {
    response(500, "Hash algorithm '$algo' is not supported.");
}
$raw = file_get_contents('php://input');
if ($hash !== hash_hmac($algo, $raw, $hookSecret)) {
    forbidden();
}
//判断事件类型
switch ($_SERVER['HTTP_X_GITHUB_EVENT'] ?? null) {
    case 'ping': {
        die('pong');
    } break;
    case 'push': {
    } break;
    default: {
        badRequest();
    }
}
//pull
exec("nohup sh -c 'git fetch origin master && git reset --hard FETCH_HEAD && yarn install && yarn build' > latest_update.log 2>&1 &");
die('ok');
?>
