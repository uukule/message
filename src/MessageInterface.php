<?php


namespace uukule;

/**
 * @method MessageInterface url(string $url, string $appid) 设置跳转地址
 * @method MessageInterface access_token(string $access_token)
 * @method MessageInterface template_id(string $id)
 * @method MessageInterface get_templates() 获取全部模板
 * Interface MessageInterface
 * @package uukule
 */
interface MessageInterface
{

    public function __construct(array $config);

    /**
     * 使用队列发送
     * @param bool $is_queue
     * @return MessageInterface
     */
    public function queue(bool $is_queue): MessageInterface;

    /**
     * 接收者
     * @param string|array $user_sign
     * @return MessageInterface
     */
    public function touser($user_sign): MessageInterface;

    /**
     * 发送者
     * @param string $user_sign
     * @return MessageInterface
     */
    public function fromuser(string $user_sign): MessageInterface;

    /**
     * 消息主题
     * @param string $subject
     * @return MessageInterface
     */
    public function subject(string $subject): MessageInterface;

    /**
     * 发送内容
     * @param array $data
     * @return MessageInterface
     */
    public function data(array $data): MessageInterface;

    /**
     * 发送
     * @return bool
     */
    public function send(): bool;

    /**
     * 队列发送
     * @param array $param
     * @return bool
     */
    public function queueSend(string $msgid, array $param): bool;

    /**
     * 数据验证
     * @return bool
     */
    public function check(): bool;

    /**
     * 消息列表
     * @param array|string $param
     * @param int $page
     * @param int $rows
     * @return array
     */
    public function list($param, int $page, int $rows): array;

    /**
     * 获取消息详情
     * @param string| $info
     * @return array
     */
    public function info(string $info): array;

    /**
     * 设置发送时间
     * @param string|int $time
     * @return MessageInterface
     */
    public function pushTime($time):MessageInterface;

    /**
     * 撤消消息
     * @param string $msgid
     * @return bool
     */
    public function revoke(string $msgid):bool;

    /**
     * 组消息列表
     * @param array $param 条件
     * @param int $page
     * @param int $rows
     * @return array
     */
    public function groupMsgList($param, int $page, int $rows):array;
    /**
     * 用户消息列表
     * @param array $param 条件
     * @param int $page
     * @param int $rows
     * @return array
     */
    public function userMsgList($param, int $page, int $rows):array;


    /**
     * 回调处理
     * @param array $param
     * @return mixed
     */
    public function callback(array $param);

}