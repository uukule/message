<?php


namespace uukule\message\core;

use Redis;

class Queue
{
    protected $queue_name = 'message:queue';
    /**
     * @var Redis|null
     */
    protected $handle = null;

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
        $this->handle = $redis;
    }

    public function push(Data $data): void
    {
        if(is_null($this->timeout)){
            $this->handle->lPush($this->queue_name, serialize($data));
        }else{
            $this->handle->lPush("{$this->queue_name}:{$data->groupMsgid()}", serialize($data));
            $this->handle->zAdd('message:timer', $this->timeout, $data->groupMsgid());
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
            if ($msgids = $this->handle->zRangeByScore('message:timer', 0, time())) {
                foreach ($msgids as $msgid) {
                    while ($val = $this->handle->lPop("{$this->queue_name}:{$msgid}")) {
                        $this->handle->lPush($this->queue_name, $val);
                    }

                    FormatOutput::red("\n".date('Y-m-d H:i:s')." - {$msgid}\n");
                    $this->handle->zRem('message:timer', $msgid);
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
        $this->handle->del("{$this->queue_name}:{$msgid}");
        $this->handle->zRem('message:timer', $msgid);
        return true;
    }

    /**
     * @return null|Data
     */
    public function pop()
    {
        $data = $this->handle->rPop($this->queue_name);
        $data = unserialize($data);
        return $data;
    }
}