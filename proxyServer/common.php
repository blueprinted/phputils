<?php

define('APP_ROOT', dirname(__FILE__));
define('APP_DATA', APP_ROOT."/data");
define('APP_LOG', APP_ROOT."/log");
define('ENVIRONMENT', 'development'); // development / dev / test / product
define('APP_MSG_HEAD', "return '['.date('Y/m/d H:i:s').' '.date_default_timezone_get().'] ';");
define('APP_BR', strtolower(substr(PHP_SAPI, 0, 3)) == 'cli' ? PHP_EOL : "<br/>");
define('USER_AGENT', isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'webproxy-1.0.0');
define('APP_START_TIME', time());
define("APP_JSON_UNESCAPED_UNICODE", defined("JSON_UNESCAPED_UNICODE") ? JSON_UNESCAPED_UNICODE : 0);

date_default_timezone_set('PRC');

if (ENVIRONMENT == 'product') {
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
    ini_set("display_errors", "On");
} else {
    error_reporting(E_ALL);
    ini_set("display_errors", "On");
}

require APP_ROOT . '/includes/inc.constans.php';
require APP_ROOT . '/includes/func.common.php';
require APP_ROOT . '/includes/class.proxyUtil.php';
require APP_ROOT . '/includes/class.curlUtil.php';
require APP_ROOT . '/vendor/autoload.php';
require APP_ROOT . '/includes/func.log.php';

function format_echo($msg)
{
    echo eval(APP_MSG_HEAD) . $msg . APP_BR;
    return true;
}

//注册shutdown函数
register_shutdown_function('shutDownHandler');

/**
 * 用于注册register_shutdown_function的shutdown回调函数
 * 主要用于发送脚本运行情况，在线调试等信息
 */
function shutDownHandler()
{
    //处理脚本PHP错误
    $error = error_get_last();
    if (is_null($error)) {
        return false;
    }

    $errorTypeArr = array (
        E_ERROR              => 'E_ERROR',
        E_WARNING            => 'E_WARNING',
        E_PARSE              => 'E_PARSE',
        E_NOTICE             => 'E_NOTICE',
        E_CORE_ERROR         => 'E_CORE_ERROR',
        E_CORE_WARNING       => 'E_CORE_WARNING',
        E_COMPILE_ERROR      => 'E_COMPILE_ERROR',
        E_COMPILE_WARNING    => 'E_COMPILE_WARNING',
        E_USER_ERROR         => 'E_USER_ERROR',
        E_USER_WARNING       => 'E_USER_WARNING',
        E_USER_NOTICE        => 'E_USER_NOTICE',
        E_STRICT             => 'E_STRICT',
        E_RECOVERABLE_ERROR  => 'E_RECOVERABLE_ERROR',
        E_DEPRECATED         => 'E_DEPRECATED',
        E_USER_DEPRECATED    => 'E_USER_DEPRECATED',
    );
    if (empty($error) || !array_key_exists($error['type'], $errorTypeArr)) {
        // This error code is not included in error_reporting
        format_echo("This error code is not included in error_reporting:" . json_encode($error));
        return false;
    }
    $errorType = $errorTypeArr[$error['type']];
    $errstr = strip_tags($error['message']);
    $myerror = "$errstr <br>File：{$error['file']} <br>Line：{$error['line']}";
    $myerror = 'Type：<strong>['.$errorType.']</strong><br>' . $myerror;
    appendlog('php_error[' . $errorType . '] ' . $myerror, 'warning');
    return true;
}

/**
 * 记录日志
 */
function appendlog($logMsg, $logLevel = 'warning', $channel = "114yygh", $context = array(), $extra = null)
{
    return monolog_appendlog((isset($_SERVER['REQUEST_URI']) ? "URI={$_SERVER['REQUEST_URI']}" : 'URI=_null_') . ' ' . $logMsg, $logLevel, $channel, $context, $extra);
}

function get_curl_common_header()
{
    return array(
        "Accept: application/json, text/javascript, */*; q=0.01",
        "Accept-Encoding: gzip, deflate",
        "Accept-Language: zh-CN,zh;q=0.9,en;q=0.8,zh-TW;q=0.7,fr;q=0.6,de;q=0.5",
        "Cache-Control: no-cache",
    );
}
function get_curl_simulator_header($type = 'get')
{
    $common_header = array(
        "Pragma: no-cache",
        "Cache-Control: no-cache",
        "Accept: application/json, text/plain, */*",
        "Request-Source: PC",
        "User-Agent: " . USER_AGENT,
        "Sec-Fetch-Mode: cors",
        "Sec-Fetch-Site: same-origin",
        "Origin: " . MAIN_URL_SCHEME.'://'.MAIN_URL_DOMAIN,
        "Sec-Fetch-Dest: empty",
        "Referer: " . MAIN_URL_SCHEME.'://'.MAIN_URL_DOMAIN.'/',
        "Accept-Encoding: gzip, deflate, br",
        "Accept-Language: zh-CN,zh;q=0.9,en;q=0.8,zh-TW;q=0.7,fr;q=0.6,de;q=0.5,ja;q=0.4",
    );
    if (strtolower($type) == 'post') {
        $common_header[] = "Content-Type: application/json;charset=UTF-8";
    }
    return $common_header;
}

function get_common_curl_options()
{
    return array(
        CURLOPT_REFERER => isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '',
        CURLOPT_USERAGENT => USER_AGENT,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT => 5,
        CURLOPT_ENCODING => "gzip", //由curl来解压gzip内容
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_FORBID_REUSE => true,
    );
}

function get_user_cookiefile($uid, $pid = 0)
{
    return preg_match('/^\d+$/', $uid) ? APP_DATA."/cookiefile/cookiefileU".sprintf("%011d", intval($uid))."P".sprintf("%011d", intval($pid)).".txt" : false;
}
