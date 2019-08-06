<?php
/**
 *  redis操作类(单列)
 *
 */

class redisUtil
{
    public $unit;
    public $link = false;
    public $conf = array();
    private $redis = null;//当前redis
    private static $instance = array();

    /**
     *  构造方法
     *  @return void
     */
    private function __construct($unit)
    {
        global $_RDSCFG;
        $this->redis = new Redis();
        if (is_array($unit)) {
            $this->unit = "{$unit['host']}:{$unit['port']}";
            $this->conf = $unit;
            $this->connect($this->conf);
        } else {
            $this->unit = $unit;
            $this->conf = $this->get_conf($_RDSCFG, $unit);
            $this->connect($this->conf);
        }
    }
    private function __clone()
    {
    }

    /**
     *  获取redis单例
     *  @return Object
     */
    public static function getInstance($unit)
    {
        if (is_array($unit)) {
            $key = "{$unit['host']}:{$unit['port']}";
        } else {
            $key = $unit;
        }
        if (!isset(self::$instance[$key]) || !(self::$instance[$key] instanceof self)) {
            self::$instance[$key] = new self($unit);
        }
        return self::$instance[$key];
    }

    /**
     *  获取配置
     *  @return Array
     */
    public function get_conf()
    {
        $args = func_get_args();
        $conf = @array_shift($args);
        foreach ($args as $k) {
            if (is_array($conf) && isset($conf[$k])) {
                $conf = $conf[$k];
            } else {
                $conf = null;
                break;
            }
        }
        return is_array($conf) ? $conf : array();
    }

    /**
     *  redis连接
     *  @return Boolean
     */
    protected function connect($config = null)
    {
        if ($config === null) {
            $config = $this->conf;
        }
        $config['timeout'] = isset($config['timeout']) ? $config['timeout'] : 0;
        $config['pconnect'] = isset($config['pconnect']) ? $config['pconnect'] : 0;
        if ($config['pconnect']) {
            if ($this->redis->pconnect($config['host'], $config['port'], $config['timeout']) === false) {
                return false;
            }
        } else {
            if ($this->redis->connect($config['host'], $config['port'], $config['timeout']) === false) {
                return false;
            }
        }
        if (isset($config['auth']) && $config['auth'] && false === $this->redis->auth($config['auth'])) {
            return false;
        }
        return $this->link = true;
    }

    /**
     *  redis重连
     *  @return Boolean/NULL [null:不需要重连,true:重连成功,false:重连失败]
     */
    public function reconnect()
    {
        if (!$this->ping(true)) {
            return $this->connect();
        }
        return null;
    }

    /**
     *    redis setOption
     *    @param $optKey String 要设置的key
     *    @param $optValue String 要设置的value
     *    @return Boolean
     */
    public function setOption($optKey, $optValue) {
        return $this->redis ? $this->redis->setOption($optKey, $optValue) : false;
    }

    /**
     *    redis scan
     *    @param $iterator String LONG (reference): Iterator, initialized to NULL STRING
     *    @param $pattern String Pattern to match LONG
     *    @param $count Integer  Count of keys per iteration (only a suggestion to Redis)
     *    @return Array / Boolean
     */
    public function scan(&$iterator, $pattern = '*', $count = 0) {
        return $this->redis ? $this->redis->scan($iterator, $pattern, $count) : false;
    }

    /**
     *  redis exists
     *  @param $key String 检查的key
     *  @param $md5key Boolean 是否先md5(key)再exists [true:是,false:否] 缺省false
     *  @return Boolean
     */
    public function exists($key, $md5key = false)
    {
        if ($md5key) {
            $key = md5($key);
        }
        //exists：检查key是否存在 原生方法 存在返回true 失败返回false
        return $this->redis ? ($this->redis->exists($key) ? true : false) : false;
    }

    /**
     *  redis set
     *  @param $key String 要set的key
     *  @param $value Mixed 要set的value
     *  @param $serialize Boolean 是否先序列化value再set [true:是,false:否] 缺省false
     *  @param $md5key Boolean 是否先md5(key)再set [true:是,false:否] 缺省false
     *  @return Boolean
     */
    public function set($key, $value, $serialize = false, $md5key = false)
    {
        if ($md5key) {
            $key = md5($key);
        }
        //set：写入key 和 value 原生方法set成功返回true 失败返回false
        return $this->redis ? $this->redis->set($key, $serialize ? @serialize($value) : $value) : false;
    }

    /**
     *  redis setex
     *  @param $key String 要set的key
     *  @param $expire Integer 过期时间
     *  @param $value Mixed 要set的value
     *  @param $serialize Boolean 是否先序列化value再set [true:是,false:否] 缺省false
     *  @param $md5key Boolean 是否先md5(key)再set [true:是,false:否] 缺省false
     *  @return Boolean
     */
    public function setex($key, $expire, $value, $serialize = false, $md5key = false)
    {
        if ($md5key) {
            $key = md5($key);
        }
        //set：写入key 和 value 原生方法setex成功返回true 失败返回false
        return $this->redis ? $this->redis->setex($key, $expire, $serialize ? @serialize($value) : $value) : false;
    }

    /**
     *  redis get
     *  @param $key String 要get的key
     *  @param $unserialize Boolean 是否对get的值反序列化 [true:是,false:否] 缺省false
     *  @param $md5key Boolean 是否先md5(key)再get [true:是,false:否] 缺省false
     *  @return Mixed 失败返回false
     */
    public function get($key, $unserialize = false, $md5key = false)
    {
        if ($md5key) {
            $key = md5($key);
        }
        //get：读取某个key的值，如果key不存在，返回FALSE
        return $this->redis ? ($unserialize ? @unserialize($this->redis->get($key)) : $this->redis->get($key)) : false;
    }

    /**
     *  redis delete
     *  @param $key String 要delete的key
     *  @param $md5key Boolean 是否先md5(key)再delete [true:是,false:否] 缺省false
     *  @return Integer/Boolean(false)
     */
    public function delete($key, $md5key = false)
    {
        if ($md5key) {
            $key = md5($key);
        }
        //delete: 删除指定key的值 返回已删除key的个数(长整数)
        return $this->redis ? $this->redis->delete($key) : false;
    }

    /**
     *  获取指定的(1个或多个)键的值
     *  @param $keys Array 要获取的keys 如 array('key1', 'key2')
     *  @param $unserialize Boolean 是否对getMultiple的值反序列化 [true:是,false:否] 缺省true
     *  @param $md5key Boolean 是否先md5(key)再getMultiple [true:是,false:否] 缺省false
     *  @return Array
     */
    public function getMultiple($keys, $unserialize = true, $md5key = false)
    {
        if (!$this->redis) {
            return array();
        }
        if ($md5key) {
            foreach ($keys as $k => $v) {
                $keys[$k] = md5($v);
            }
        }
        $array = $this->redis->getMultiple($keys);
        if ($unserialize && is_array($array)) {
            foreach ($array as $k => $v) {
                $array[$k] = @unserialize($v);
            }
        }
        return $array;
    }

    /**
     *  key值自增
     *  @param $key String 要自增的key
     *  @param $md5key Boolean 是否先md5(key)再incr [true:是,false:否] 缺省false
     *  @return Boolean
     */
    public function incr($key, $md5key = false)
    {
        if (!$this->redis) {
            return false;
        }
        if ($md5key) {
            $key = md5($key);
        }
        //原生方法 自增成功返回自增后的值 失败返回false
        return $this->redis->incr($key);
    }

    /**
     *  key值自增
     *  @param $key String 要自增的key
     *  @param $inc Integer 自增的量 缺省1
     *  @param $md5key Boolean 是否先md5(key)再incrBy [true:是,false:否] 缺省false
     *  @return Boolean
     */
    public function incrBy($key, $inc = 1, $md5key = false)
    {
        if (!$this->redis) {
            return false;
        }
        if ($md5key) {
            $key = md5($key);
        }
        //原生方法 自增成功返回自增后的值 失败返回false
        return $this->redis->incrBy($key, $inc);
    }

    /**
     *  key值自减
     *  @param $key String 要自减的key
     *  @param $md5key Boolean 是否先md5(key)再decr [true:是,false:否] 缺省false
     *  @return Boolean
     */
    public function decr($key, $md5key = false)
    {
        if (!$this->redis) {
            return false;
        }
        if ($md5key) {
            $key = md5($key);
        }
        //原生方法 自减成功返回自减后的值 失败返回false
        return $this->redis->decr($key);
    }

    /**
     *  key值自减
     *  @param $key String 要自增的key
     *  @param $dec Integer 自减的量 缺省1
     *  @param $md5key Boolean 是否先md5(key)再decrBy [true:是,false:否] 缺省false
     *  @return Boolean
     */
    public function decrBy($key, $dec = 1, $md5key = false)
    {
        if (!$this->redis) {
            return false;
        }
        if ($md5key) {
            $key = md5($key);
        }
        //原生方法 自减成功返回自减后的值 失败返回false
        return $this->redis->decrBy($key, $dec);
    }

    /**
     *  清空当前数据库
     *  @return Boolean
     */
    public function flushDB()
    {
        return $this->redis ? $this->redis->flushDB() : false;
    }

    /**
     *  清空所有数据库
     *  @return Boolean
     */
    public function flushAll()
    {
        return $this->redis ? $this->redis->flushAll() : false;
    }

    /**
     *  检测redis连接是否断开
     *  @param $return_boolean Boolean 是否返回布尔值 [true:是,false:否]
     *  @return String/Boolean
     *  原生redis->ping在连接正常时返回 string(5) "+PONG" 连接丢失时返回 bool(false)
     */
    public function ping($return_boolean = false)
    {
        return $this->redis ? ($return_boolean ? ($this->redis->ping() === '+PONG' ? true : false) : $this->redis->ping()) : false;
    }

    /**
     *  redis close
     *  @return Boolean
     */
    public function close()
    {
        return $this->redis ? $this->redis->close() : true;
    }
    /**
     * [multi_hGet description]
     * @param  [type]  $key_arr
     * @param  [type]  $field
     * @param  boolean $is_md5
     * @return [type]
     */
    public function multi_hMGet($key_arr, array $field, $is_md5 = true)
    {
        if (!is_array($key_arr)) {
            $key_arr = explode(',', $key_arr);
        }
        try {
            $r = $this->redis->multi(Redis::PIPELINE);
            foreach ($key_arr as $redis_key) {
                $is_md5 == true && ($redis_key = md5($redis_key));
                $r->hMGet($redis_key, $field);
            }
            $ret = $r->exec();
            return $ret;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * @param $data  article_id => create_time
     * */
    public function multi_zAdd($key, array $data)
    {
        try {
            $r = $this->redis->multi(Redis::PIPELINE);
            foreach ($data as $id => $score) {
                $r->zAdd($key, intval($score), $id);
            }
            $ret = $r->exec();
            return $ret;
        } catch (Exception $e) {
            return false;
        }
    }

    public function lPush($key, $value)
    {
        return $this->redis ? $this->redis->lPush($key, $value) : false;
    }

    public function lPushx($key, $value)
    {
        return $this->redis ? $this->redis->lPushx($key, $value) : 0;
    }

    public function rPush($key, $value)
    {
        return $this->redis ? $this->redis->rPush($key, $value) : false;
    }

    public function rPushx($key, $value)
    {
        return $this->redis ? $this->redis->rPushx($key, $value) : 0;
    }

    public function rename($key, $key2)
    {
        return $this->redis ? $this->redis->rename($key, $key2) : false;
    }

    public function renameNx($key, $key2)
    {
        return $this->redis ? $this->redis->renameNx($key, $key2) : false;
    }

    public function subscribe($channels, $callback)
    {
        return $this->redis ? $this->redis->subscribe($channels, $callback) : false;
    }
    
    public function hSet($key, $field, $value) {
        // LONG 1 if value didn't exist and was added successfully, 0 if the value was already present and was replaced, FALSE if there was an error.
        return $this->redis ? $this->redis->hSet($key, $field, $value) : false;
    }
    public function hGet($key, $field) {
        // STRING The value, if the command executed successfully BOOL FALSE in case of failure
        return $this->redis ? $this->redis->hGet($key, $field) : false;
    }
    public function hKeys($key) {
        // An array of elements, the keys of the hash. This works like PHP's array_keys().
        return $this->redis ? $this->redis->hKeys($key) : array();
    }
    public function hExists($key, $field) {
        // BOOL: If the member exists in the hash table, return TRUE, otherwise return FALSE.
        return $this->redis ? $this->redis->hExists($key, $field) : false;
    }
    public function hIncrBy($key, $field, $step = 1) {
        // LONG the new value
        return $this->redis ? $this->redis->hIncrBy($key, $field, $step) : 0;
    }
    
    public function zAdd($key, $score, $value) {
        // Return value Long 1 if the element is added. 0 otherwise. 只有当增加了才返回1 其他都返回0 不增加但值被更新也返回0
        return $this->redis ? $this->redis->zAdd($key, $score, $value) : 0;
    }
    public function zCard($key) {
        // Return value Long, the set's cardinality
        return $this->redis ? $this->redis->zCard($key) : 0;
    }
    public function zCount($key, $start, $end) {
        // Return value LONG the size of a corresponding zRangeByScore.
        return $this->redis ? $this->redis->zCount($key, $start, $end) : 0;
    }
    public function zRank($key, $member) {
        // Return value Long, the item's score. 返回的是排序值从0开始 key不存在或成员不存在返回false
        return $this->redis ? $this->redis->zRank($key, $member) : false;
    }
    public function zRevRank($key, $member) {
        // Return value Long, the item's score. 返回的是排序值从0开始 key不存在或成员不存在返回false
        return $this->redis ? $this->redis->zRevRank($key, $member) : false;
    }
    public function zIncrBy($key, $deltaScore, $member) {
        // Return value DOUBLE the new value 返回更新后的值 如果key不存在或member不存在则初始为0并返回自增后的值
        return $this->redis ? $this->redis->zIncrBy($key, $deltaScore, $member) : false;
    }

    /**
     * @param $start Integer 起始偏移量
     * @param $end Integer 结束偏移量
     */
    public function zRange($key, $start, $end, $withscores = false) {
        // Return value Array containing the values in specified range.
        return $this->redis ? $this->redis->zRange($key, $start, $end, $withscores) : array();
    }
}
