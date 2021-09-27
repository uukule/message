<?php


namespace uukule\message\core;

use Redis;

class Queue
{
    const MESSAGE_QUEUE_KEY = 'message:queue:%s';
    const MESSAGE_QUEUE_GROUP_LIST = 'message:group:%s';

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
            $listName = sprintf(self::MESSAGE_QUEUE_KEY, $data->config['type']);
            self::$handle->lPush($listName, serialize($data));
        }else{
            $timeoutListName = sprintf(self::MESSAGE_QUEUE_GROUP_LIST, $data->groupMsgid());
            self::$handle->lPush($timeoutListName, serialize($data));
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
                    $groupList = sprintf(self::MESSAGE_QUEUE_GROUP_LIST, $msgid);
                    while ($val = self::$handle->lPop($groupList)) {
                        $class = unserialize($val);
                        $listName = sprintf(self::MESSAGE_QUEUE_KEY, $class->config['type']);
                        self::$handle->lPush($listName, $val);
                        unset($listName);
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
        $groupList = sprintf(self::MESSAGE_QUEUE_GROUP_LIST, $msgid);
        self::$handle->del($groupList);
        self::$handle->zRem('message:timer', $msgid);
        return true;
    }

    /**
     * @return null|Data
     */
    public function pop(string $type)
    {
        $queueKey = sprintf(self::MESSAGE_QUEUE_KEY, $type);
        $data = self::$handle->rPop($queueKey);
        $data = unserialize($data);
        return $data;
    }
}
