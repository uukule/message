<?php


namespace uukule\message\core;

use Redis;

class Queue
{
    protected $queue_name = 'message:queue';
    /**
     * @var Redis|null
     */
    protected static $handle = null;

    /**
     * @var null|int 定时
     */
    protected $timeout = null;

    public function __construct()
    {
        $config = config('message.redis');
        $redis = new \Redis();
        $redis->connect($config['host'], $config['port']);
        $redis->auth($config['password']);
        self::$handle = $redis;
    }


    /**
     * @return Redis
     */
    public function redis() : \Redis
    {
        return self::$handle;
    }

    public function push(Data $data): void
    {
        if(is_null($this->timeout)){
            self::$handle->lPush($this->queue_name, serialize($data));
        }else{
            self::$handle->lPush("{$this->queue_name}:{$data->groupMsgid()}", serialize($data));
            self::$handle->zAdd('message:timer', $this->timeout, $data->groupMsgid());
        }

    }

    /**
     * @param string|int $datetime
     * @return Queue
     */
    public function timeout(string $datetime): Queue
    {
        $time = is_numeric($datetime) ? (int)$datetime : $time = strtotime($datetime);
        $this->timeout = $time;
        return $this;
    }

    public function timer()
    {
        while (true) {
            if ($msgids = self::$handle->zRangeByScore('message:timer', 0, time())) {
                foreach ($msgids as $msgid) {
                    while ($val = self::$handle->lPop("{$this->queue_name}:{$msgid}")) {
                        self::$handle->lPush($this->queue_name, $val);
                    }

                    FormatOutput::red("\n".date('Y-m-d H:i:s')." - {$msgid}\n");
                    self::$handle->zRem('message:timer', $msgid);
                }
            } else {
                sleep(5);
                FormatOutput::green(".");
                if (time() % 300 === 0){
                    FormatOutput::green("\n=====" . date('Y-m-d H:i:s') . "=======\n");
                }
            }
        }
    }

    /**
     * 撤消
     * @param string $msgid
     * @return bool
     */
    public function revoke(string $msgid):bool
    {
        self::$handle->del("{$this->queue_name}:{$msgid}");
        self::$handle->zRem('message:timer', $msgid);
        return true;
    }

    /**
     * @return null|Data
     */
    public function pop()
    {
        $data = self::$handle->rPop($this->queue_name);
        $data = unserialize($data);
        return $data;
    }
}