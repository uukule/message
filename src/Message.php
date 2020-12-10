<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2019 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

namespace uukule;

/**
 * @see \uukule\MessageAbstract
 */
class Message
{

    /**
     * @var array 文件的实例
     */
    public static $instance = [];

    /**
     * @var object 操作句柄
     */
    public static $handler;

    public function __call($method, $args)
    {
        $config = config('message');
        if (array_key_exists($method, $config)) {
            return $instance = $this->init($method);
        } else {
            $instance = $this->init();
            return $instance->$method(...$args);
        }
    }


    /**
     * 自动初始化缓存
     * @param string|array $config 配置数组
     * @return MessageAbstract
     * @throws \Exception
     */
    public static function init($config = null)
    {
        if (is_null($config)) {
            $config = config('message.local');
            return self::connect($config);
        }elseif (is_string($config)) {
            return self::connect(config('message.' . $config));
        } elseif (is_array($config)) {
            return self::connect($config);
        } else {
            throw new \Exception('请指定文件驱动类型');
        }
    }

    /**
     * 连接文件驱动
     * @access public
     * @param array $config 配置数组
     * @param bool|string $name 缓存连接标识 true 强制重新连接
     * @return Driver
     */
    public static function connect(array $config = [], $name = false)
    {
        $type = $config['type'];

        if (false === $name) {
            $name = md5(serialize($config));
        }

        if (true === $name || !isset(self::$instance[$name])) {
            $_value = ucwords(str_replace(['-', '_'], ' ', $type));
            $studly_type = str_replace(' ', '', $_value);
            $class = false === strpos($type, '\\') ?
                '\\uukule\\message\\driver\\' . ucwords($studly_type) :
                $type;

            if (true === $name) {
                return new $class($config);
            }

            self::$instance[$name] = new $class($config);
        }

        return self::$instance[$name];
    }


    /**
     * Handle dynamic, static calls to the object.
     *
     * @param string $method
     * @param array $args
     * @return mixed
     *
     * @throws \RuntimeException
     */
    public static function __callStatic($method, $args)
    {
        $instance = self::init();
        return $instance->$method(...$args);
    }
}
