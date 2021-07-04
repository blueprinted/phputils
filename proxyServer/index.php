<?php

/*
php实现的代理请求服务
支持代理 get post put head delete 这5中方法
请求本身只支持 get post 方法
*/

require __DIR__ . "/common.php";

// 判断请求本身是get还是post
$reqMethod = isset($_SERVER['REQUEST_METHOD']) ? strtoupper($_SERVER['REQUEST_METHOD']) : 'unknown'; 
if (!in_array($reqMethod, array('GET', 'POST'), true)) {
    apimessage(88, "不支持的请求方式");
}

$proxyUrl = getVar('proxyUrl');
$proxyUMethod = getVar('proxyUMethod');
$proxyReferer = getVar('proxyReferer');
$proxyUserAgent = USER_AGENT;

if (is_null($proxyUrl)) {
    apimessage(1002, "参数错误proxyUrl");
}
$proxyUMethod = is_null($proxyUMethod) ? 'GET' : strtoupper($proxyUMethod);
$proxyReferer = is_null($proxyReferer) ? '' : $proxyReferer;
if (!in_array($proxyUMethod, array('GET', 'POST', 'PUT', 'HEAD', 'DELETE'), true)) {
    apimessage(89, "不支持代理请求方式");
}
// 解析代理的url
$pUrlInfo = parse_url($proxyUrl);
if ($pUrlInfo === false || is_array($pUrlInfo)) {
    apimessage(90, "解析proxyUrl失败");
}

$sessID = "";
if (!__costom_function_is_session_started()) {
    ini_set('session.gc_maxlifetime', 3600); // 默认值1440(12分钟)
    session_start();
    $sessID = session_id(); // 当前会话ID
}

// if ($_SERVER['REQUEST_METHOD'] === 'POST') {
// }

/**
 * @param $code Integer 状态码
 * @param $msg String   提示消息
 * @param $data String/Array 传递数据
 * @return void
 */
function apimessage($code = 0, $msg = 'succ', $data = array())
{
    $code = intval($code);
    if (is_array($data) && empty($data)) {
        $data = new stdclass();
    }
    $info = array('code'=>$code,'msg'=>$msg,'data'=>$data);
    @header("Content-type:text/html;charset=utf-8");
    @ob_clean();
    echo json_encode($info, defined("JSON_UNESCAPED_UNICODE") ? JSON_UNESCAPED_UNICODE : 0);
    exit();
}

function getVar($key, $type = 'GP')
{
    $type = strtoupper($type);
    switch ($type) {
        case 'G':
            $var = &$_GET;
            break;
        case 'P':
            $var = &$_POST;
            break;
        case 'C':
            $var = &$_COOKIE;
            break;
        default:
            if (isset($_GET[$key])) {
                $var = &$_GET;
            } else {
                $var = &$_POST;
            }
            break;
    }
    return isset($var[$key]) ? $var[$key] : null;
}

/** referer https://blog.csdn.net/zhezhebie/article/details/102678031
 * 判断session是否处于开启状态
 * @return boolean [description]
 */
function __costom_function_is_session_started()
{
    $sapi_type = php_sapi_name();
    if ($sapi_type !== false && substr(strtolower($sapi_type), 0, 3) !== 'cli') {
        if (version_compare(phpversion(), '5.4.0', '>=')) {
            return session_status() === PHP_SESSION_ACTIVE ? true : false;
        } else {
            return session_id() === '' ? false : true;
        }
    }
    return false;
}

function get_user_cookiefile($sessId)
{
    return preg_match('/^[\w\.\-]+$/', $sessId) ? APP_DATA."/cookiefile/cookie_{$sessId}.txt" : false;
}

function get_proxy_curl_options()
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
