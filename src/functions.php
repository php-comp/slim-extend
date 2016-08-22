<?php
/**
 * @author inhere
 * @desc some helper function
 */

/**
 * @param $key
 * @param null $default
 * @return mixed|null
 */
function config($key,$default=null)
{
    return \Slim::config($key,$default);
}

function slimExt_tl($key, $args = [], $default = 'No translate.')
{
    return \Slim::get('language')->tl($key, $args, $default);
}
function slimExt_tran($key, $args = [], $default = 'No translate.')
{
    return \Slim::get('language')->tl($key, $args, $default);
}

function is_loc_env()
{
    return defined('RUNTIME_ENV') && RUNTIME_ENV === 'loc';
}

function is_dev_env()
{
    return defined('RUNTIME_ENV') && RUNTIME_ENV === 'dev';
}

function is_pdt_env()
{
    return defined('RUNTIME_ENV') && RUNTIME_ENV === 'pdt';
}

/**
 * page alert messages
 * @param mixed $msg
 * @return array|bool
 */
function alert_messages($msg='')
{
    // get all alert message
    if ( !$msg ) {
        return Slim::$app->request->getMessage();
    }

    // add a new alert message
    Slim::$app->response->withMessage($msg);

    return true;
}


/**
 * xhr 响应的数据格式
 * @param array|int $data 输出的数据
 * @param int $code 状态码
 * @param string $msg
 * @return array
 * @throws \RuntimeException
 */
function format_messages($data, $code = 0, $msg = '')
{
    // if $data is integer format_messages(int $code, string $msg )
    if ( is_numeric($data) ) {
        $jsonData = [
            'code'     => (int)$data,
            'msg'      => $code,
            'data'     => [],
        ];
    } else {
        $jsonData = [
            'code'     => (int)$code,
            'msg'      => $msg ?: 'successful!',
            'data'     => (array)$data,
        ];
    }

    return $jsonData;
}

/**
 * suggest use : `$response->withRedirect($url, 301);`
 * @param $url
 * @param int $status
 * @return slimExt\base\Response
 */
function redirect_to($url='/', $status=301)
{
    return Slim::get('response')->withStatus($status)->withHeader('Location', $url);
}

/**
 * @param $file
 * @return mixed
 */
function get_extension($file)
{
    return pathinfo($file, PATHINFO_EXTENSION);
}
