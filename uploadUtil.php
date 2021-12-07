<?php

define('UPLOADUTIL_DATA_ROOT', '/tmp');

/**
 * 上传操作类
 */
class uploadUtil
{
    private static $instance = null;
    protected static $options = array(); // 当前选项
    protected static $defaultOpts = array( // 缺省选项
        'rootDir' => UPLOADUTIL_DATA_ROOT, //文件上传保存的根目录 结尾需不带 /
        'prefixDir' => '',//文件上传的保存路径（相对于设定的rootDir）结尾需不带 / 可以留空
        'saveName' => '',//上传文件的文件名（带后缀）保存规则，如果留空则上传的文件名不变
        'replace' => false,//存在同名文件是否是覆盖，默认为false
        'maxSize' => 2 * 1024 * 1024,//允许上传的最大文件大小 byte
        'exts' => array(),//允许上传的文件后缀（留空为不限制），使用数组或者逗号分隔的字符串设置，默认为空
        'mimes' => array(),//允许上传的文件mime类型（留空为不限制），使用数组或者逗号分隔的字符串设置，默认为空
        'hash' => false,//是否生成文件的hash编码 默认为true
    );

    public function __construct($options = array())
    {
        self::setOptions($options);
    }

    private function __clone()
    {
    }

    public function __destruct()
    {
    }

    public static function getInstance($options = array())
    {
        if (!isset(self::$instance)) {
            self::$instance = new self($options);
        }
        return self::$instance;
    }

    /**
     *  设置选项
     */
    private static function setOptions($options = array())
    {
        self::$options = array_replace(self::$defaultOpts, self::$options, $options);
        return self::$options;
    }

    /**
     *  功能 批量上传
     */
    public static function uploadBatch($fields = array(), $options = array())
    {
        $resu = array();
        $options = array_replace(self::$options, $options);
        foreach ($_FILES as $field => $file) {
            if (empty($fields) || in_array($field, $fields, true)) {
                $resu[$field] = self::upload($file, $options);
            }
        }
        return $resu;
    }

    /**
     *  功能 获取单个上传的文件
     *  @param $file Arary 文件$_FILES一个的子元素 必须
     *  @param $options 参看 setOptions() 方法的 $defaultOpts
     *  @return Array array('code'=>Integer,'msg'=>'succ','data'=>array('filedir'=>String,'filename'=>String,'name'=>String,'type'=>String,'size'=>Integer,'error'=>Integer))
     *  code含义：
     *  99: 未知错误
     *  98: 文件参数错误
     *  25: 移动临时文件失败
     *  24: 文件保存的目录不存在且创建失败
     *  23: 文件大小超过$filesize的限制
     *  22: 不允许的文件MIME
     *  21: 不允许的文件类型
     *  20: 获取文件名后缀失败
     *  7: 写入文件失败
     *  6: 找不到临时文件夹
     *  5: 上传文件大小为0
     *  4: 没有文件被上传
     *  3: 文件只有部分被上传
     *  2: 文件的大小超过了 HTML 表单中 MAX_FILE_SIZE 选项指定的值
     *  1: 文件超过了 php.ini 中 upload_max_filesize 选项限制的值
     *  0: 成功
     */
    public static function upload($file, $options = array())
    {
        $options = array_replace(self::$options, $options);
        $file = is_array($file) ? $file : (array)$file;

        if (is_array($options['exts'])) {
            $allowExts = array_map(function ($str) {
                return strtolower(trim($str));
            }, $options['exts']);
        } else {
            $allowExts = array_map(function ($str) {
                return strtolower(trim($str));
            }, explode(',', $options['exts']));
        }
        if (is_array($options['mimes'])) {
            $allowMime = array_map(function ($str) {
                return strtolower(trim($str));
            }, $options['mimes']);
        } else {
            $allowMime = array_map(function ($str) {
                return strtolower(trim($str));
            }, explode(',', $options['mimes']));
        }
        $rootDir = $options['rootDir'];
        $prefixDir = $options['prefixDir'];
        $saveName = $options['saveName'];
        $maxFilesize = $options['maxSize'];
        $allowReplace = $options['replace'];
        $hash = $options['hash'];

        $resu = array(
          'code'=>0,
          'msg'=>'succ',
          'data'=>array(
              'filedir'=>'',//文件目录(相对 UPLOADUTIL_DATA_ROOT 的路径)
              'filename'=>'',//文件名(带后缀)
              'name'=>$file['name'], //文件原名
              'type'=>$file['type'], //文件MIME 如 text/plain
              'size'=>$file['size'],
              'ext'=>'',
              'error'=>$file['error'],
          ),
        );

        if (!isset($file['tmp_name']) || !isset($file['name']) || !isset($file['size']) || !isset($file['type']) || !isset($file['error'])) {
            return array_replace_recursive($resu, array(
                'code' => 98,
                'msg' => 'file参数错误',
            ));
        }
        if ($file['error'] != 0) {
            if ($file['error'] == 1) {
                return array_replace_recursive($resu, array(
                  'code' => 1,
                  'msg' => '文件超过了 php.ini 中 upload_max_filesize 选项限制的值',
                ));
            } elseif ($file['error'] == 2) {
                return array_replace_recursive($resu, array(
                  'code' => 2,
                  'msg' => '上传文件的大小超过了 HTML 表单中 MAX_FILE_SIZE 选项指定的值',
                ));
            } elseif ($file['error'] == 3) {//文件只有部分被上传
                return array_replace_recursive($resu, array(
                  'code' => 3,
                  'msg' => '文件只有部分被上传',
                ));
            } elseif ($file['error'] == 4) {//没有文件被上传
                return array_replace_recursive($resu, array(
                  'code' => 4,
                  'msg' => '没有文件被上传',
                ));
            } elseif ($file['error'] == 5) {//上传文件大小为0
                return array_replace_recursive($resu, array(
                  'code' => 5,
                  'msg' => '上传文件大小为0',
                ));
            } elseif ($file['error'] == 6) {//找不到临时文件夹
                return array_replace_recursive($resu, array(
                  'code' => 6,
                  'msg' => '找不到临时文件夹',
                ));
            } elseif ($file['error'] == 7) {//写入文件失败
                return array_replace_recursive($resu, array(
                  'code' => 7,
                  'msg' => '写入文件失败',
                ));
            } else {//未知错误
                return array_replace_recursive($resu, array(
                  'code' => 99,
                  'msg' => '未知错误',
                ));
            }
        }
        $fileext = self::fileext($file['name']);//文件名后缀
        if ($fileext == '') {
            return array_replace_recursive($resu, array(
              'code' => 20,
              'msg' => '获取文件名后缀失败',
            ));
        }
        if (!empty($allowExts) && !in_array(strtolower($fileext), $allowExts, true)) {
            return array_replace_recursive($resu, array(
              'code' => 21,
              'msg' => '不允许的文件类型',
            ));
        }
        if (!empty($allowMime) && !in_array(strtolower($file['type']), $allowMime, true)) {
            return array_replace_recursive($resu, array(
              'code' => 22,
              'msg' => '不允许的文件MIME',
            ));
        }

        if ($file['size'] > $maxFilesize) {
            return array_replace_recursive($resu, array(
              'code' => 23,
              'msg' => '文件大小超过限制('.self::formatsize($maxFilesize).')',
            ));
        }

        $savepath = self::get_target_dir($rootDir, $prefixDir);
        if (!is_dir($savepath) && !self::mkdir_recursive($savepath, 0775)) {
            return array_replace_recursive($resu, array(
              'code' => 24,
              'msg' => '文件保存的目录不存在且创建失败['.$savepath.']',
            ));
        }

        $filename = strlen($saveName) > 0 ? $saveName : self::get_target_filename($fileext, true, 8);
        $filepath = $savepath.'/'.$filename;

        //移动临时文件
        if (function_exists("move_uploaded_file") && move_uploaded_file($file['tmp_name'], $filepath)) {
        } elseif (rename($file['tmp_name'], $filepath)) {
        } elseif (copy($file['tmp_name'], $filepath)) {
            @unlink($file['tmp_name']);
        } else {
            return array_replace_recursive($resu, array(
              'code' => 25,
              'msg' => '移动临时文件失败',
            ));
        }

        return array_replace_recursive($resu, array(
          'code'=>0,
          'msg'=>'succ',
          'data'=>array(
              'filedir'=>str_replace(($rootDir === '' ? UPLOADUTIL_DATA_ROOT : $rootDir).'/', '', $savepath),
              'filename'=>$filename,
              'name'=>$file['name'],
              'type'=>$file['type'],
              'size'=>$file['size'],
              'ext'=>$fileext,
              'error'=>$file['error'],
          ),
        ));
    }

  /**   功能 根据文件后缀获取文件保存目录
   *    @param $root_dir String 根目录 缺省将使用 UPLOADUTIL_DATA_ROOT
   *    @param $prefix_dir String 前置目录 可选 缺省'' 会拼接在 $root_dir 后
   *    @return String 文件保存目录(绝对路径) 如: /search/.../sweb/data/upload/uploadImage/2014/05
   */
    public static function get_target_dir($root_dir, $prefix_dir = '')
    {
        $file_dir = trim($root_dir) === '' ? UPLOADUTIL_DATA_ROOT : $root_dir;
        $file_dir .= (substr($file_dir, -1) == '/' ? '' : '/') . $prefix_dir;
        $file_dir .= (substr($file_dir, -1) == '/' ? '' : '/') . date('Ym') . '/' . date('Ymd') ;
        return $file_dir;
    }

  /**   功能 根据文件后缀获取文件的保存名
   *    @param $fileext String 文件后缀名
   *    @param $numeric Boolean 是否纯数字串 缺省为false [true:是, false:否]
   *    @param $rand_length Integer 附加随机串长度 缺省8
   *    @return String 文件名(带后缀) 如: 140004sdfesadfsasd1e43.png
   */
    public static function get_target_filename($fileext, $numeric = false, $rand_length = 8)
    {
        $mtime = microtime(true);
        $itime = intval($mtime);
        return date('YmdHis', intval($itime)) . strval(intval(10000*($mtime-$itime))) . self::random($rand_length, $numeric) . (strlen($fileext) > 0 ? ".{$fileext}" : "");
    }

    /** 功能 获取文件名后缀
     *  @param $filename String 带后缀的文件名 必须 如 'data.txt','data.sql'
     *  @return String ''或'sql','txt',...
     */
    public static function fileext($filename)
    {
        return strtolower(substr(strrchr(basename($filename), '.'), 1));
    }

    /** 功能 生成随机字符串
     *  @param $length Integer 生成的随机串的字符长度
     *  @param $numeric Boolean 是否纯数字串 缺省为false [true:是, false:否]
     *  @return String
     */
    public static function random($length, $numeric = false)
    {
        $seed = base_convert(md5(microtime().$_SERVER['DOCUMENT_ROOT']), 16, $numeric ? 10 : 35);
        $seed = $numeric ? (str_replace('0', '', $seed).'012340567890') : ($seed.'zZ'.strtoupper($seed));
        $hash = '';
        $max = strlen($seed) - 1;
        for ($i = 0; $i < $length; $i++) {
            $hash .= $seed[mt_rand(0, $max)];
        }
        return $hash;
    }

    public static function mkdir_recursive($pathname, $mode = 0755)
    {
        is_dir(dirname($pathname)) || self::mkdir_recursive(dirname($pathname), $mode);
        return is_dir($pathname) || @mkdir($pathname, $mode);
    }

    /** 功能 格式化字节大小
     *  @param $size Integer 文件字节数
     *  @return String 如:10.1KB, 0.99MB, ...
     */
    public static function formatsize($size)
    {
        $prec=3;
        $size = round(abs($size));
        $units = array(0=>" B ", 1=>" KB", 2=>" MB", 3=>" GB", 4=>" TB");
        if ($size==0) {
            return str_repeat(" ", $prec)."0$units[0]";
        }
        $unit = min(4, floor(log($size)/log(2)/10));
        $size = $size * pow(2, -10*$unit);
        $digi = $prec - 1 - floor(log($size)/log(10));
        $size = round($size * pow(10, $digi)) * pow(10, -$digi);
        return $size.$units[$unit];
    }
}
