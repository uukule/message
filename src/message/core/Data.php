<?php


namespace uukule\message\core;

/**
 * Class Data
 *
 * @method void|array config($config = []) 配置
 * @method void|int time(int $time = null) 提交时间
 * @method void|array data(array $data = []) 发送包
 * @method void|string touser(string $touser = '') 接收者
 * @method void|string fromuser(string $fromuser = '') 发送者
 * @method void|string msgid(string $msgid) 消息ID
 * @method void|string appid(string $appid) 应用ID
 * @method void|bool isFirstUser(string $isFirstUser) 是否为组第一条消息
 * @method void|bool isLastUser(string $isLastUser) 是否为组最后一条消息
 * @method void|string groupMsgid(string $groupMsgid) 组消息ID
 * @package uukule\message\core
 */
class Data
{

    protected $data;

    protected $touser;

    protected $fromuser;

    /**
     * 组消息ID
     * @var string
     */
    protected $groupMsgid;

    /**
     * 是否为组第一条消息
     * @var bool
     */
    protected $isFirstUser = true;
    /**
     * 是否为组最后一条消息
     * @var bool
     */
    protected $isLastUser = false;

    protected $appid;

    protected $config = [];


    public function __call($name, $arguments)
    {
        switch (count($arguments)) {
            case 0:
                return $this->$name;
                break;
            case 1:
                $this->$name = $arguments[0];
                break;
        }

    }

    public function __get($name)
    {
        return $this->$name;
    }


    public function __set($name, $value)
    {
        $this->$name = $value;
    }


}