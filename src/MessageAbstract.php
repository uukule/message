<?php


namespace uukule;

use uukule\message\core\Queue;

/**
 * @method MessageAbstract url(string $url, string $appid) 设置跳转地址
 * @method MessageAbstract access_token(string $access_token)
 * @method MessageAbstract template_id(string $id)
 * @method MessageAbstract get_templates() 获取全部模板
 * Interface MessageAbstract
 * @package uukule
 */
abstract class MessageAbstract
{

    protected $push_time = null;

    abstract public function __construct(array $config);

    /**
     * 使用队列发送
     * @param bool $is_queue
     * @return MessageAbstract
     */
    abstract public function queue(bool $is_queue): MessageAbstract;

    /**
     * 接收者
     * @param string|array $user_sign
     * @return MessageAbstract
     */
    abstract public function touser($user_sign): MessageAbstract;

    /**
     * 发送者
     * @param string $user_sign
     * @return MessageAbstract
     */
    abstract public function fromuser(string $user_sign): MessageAbstract;

    /**
     * 消息主题
     * @param string $subject
     * @return MessageAbstract
     */
    abstract public function subject(string $subject): MessageAbstract;

    /**
     * 发送内容
     * @param array|string $data
     * @return MessageAbstract
     */
    abstract public function content($data): MessageAbstract;

    /**
     * 发送
     * @return bool
     */
    abstract public function send(): bool;

    /**
     * 队列发送
     * @param array $param
     * @return bool
     */
    abstract public function queueSend(string $msgid, array $param): bool;

    /**
     * 数据验证
     * @return bool
     */
    abstract public function check(): bool;


    /**
     * 获取消息详情
     * @param string| $info
     * @return array
     */
    abstract public function info(string $info): array;

    /**
     * 设置发送时间
     * @param $time
     * @return MessageAbstract
     * @throws MessageException
     */
    public function pushTime($time): MessageAbstract
    {
        $timestamp = null;
        if (is_numeric($time)) {
            $timestamp = (int)$time;
        } elseif (is_string($time)) {
            $timestamp = strtotime($time);
        } else {
            throw new MessageException('发送时间不规范，仅支持datetime与时间戳格式', 10030);
        }
        $this->push_time = $timestamp;
        return $this;
    }

    /**
     * 撤消消息
     * @param string $msgid
     * @return bool
     */
    public function revoke(string $msgid): bool
    {
        $queue = new Queue();
        return $queue->revoke($msgid);
    }


    /**
     * 组消息列表
     * @param array $param 条件
     * @param int $page
     * @param int $rows
     * @return array
     */
    abstract public function groupMsgList($param, int $page, int $rows): array;

    /**
     * 用户消息列表
     * @param array $param 条件
     * @param int $page
     * @param int $rows
     * @return array
     */
    abstract public function userMsgList($param, int $page, int $rows): array;


    /**
     * 回调处理
     * @param array $param
     * @return mixed
     */
    abstract public function callback(array $param);

}