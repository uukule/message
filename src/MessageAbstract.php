<?php


namespace uukule;

use uukule\message\core\Model;
use uukule\message\core\Queue;
use uukule\message\core\Touser;

/**
 * @method MessageAbstract url(string $url, string $appid) 设置跳转地址
 * @method MessageAbstract access_token(string $access_token)
 * @method MessageAbstract template_id(string $id)
 * @method array getTemplates() 获取全部模板
 * @method string addTemplates() 添加模板
 * @method bool deleteTemplates() 删除模板
 * Interface MessageAbstract
 * @package uukule
 */
abstract class MessageAbstract
{

    protected $push_time = null;
    protected $from_user_sign = null;
    protected $from_user_name = null;

    abstract public function __construct(array $config);

    /**
     * 使用队列发送
     * @param bool $is_queue
     * @return MessageAbstract
     */
    abstract public function queue(bool $is_queue): MessageAbstract;


    /**
     * 发布者
     * @param string $user_sign
     * @param string $user_name
     */
    public function fromuser(string $from_user_sign, string $from_user_name): MessageAbstract
    {
        $this->from_user_sign = $from_user_sign;
        $this->from_user_name = $from_user_name;
        return $this;
    }

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
     * @return array
     */
    abstract public function send(): array;

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
     * @param string $g_msgid 群消息ID
     * @return bool
     */
    public function revoke(string $g_msgid): bool
    {
        $model = new Model();
        $model->startTrans();
        try{
            $gmsgRow = $model->where('g_msgid', $g_msgid)->find();
            $gmsgRow->save(['status' => MESSAGE_STATUS_RESET]);
            $gmsgRow->groupMsg()->save(['status' => MESSAGE_STATUS_RESET]);
            $model->commit();
            $queue = new Queue();
            return $queue->revoke($g_msgid);
        }catch (MessageException $exception){
            $model->rollback();
        }
    }


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


    /**
     * 接收者
     * @param Touser $tousers
     * @return MessageAbstract
     */
    public function touser(Touser $tousers): MessageAbstract
    {
        $this->touser = $tousers;
        return $this;
    }


    /**
     * 组消息列表
     * @param $param 条件
     * @param int $page
     * @param int $rows
     * @return array
     * @throws MessageException
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function groupMsgList($param = [], int $page = 1, int $rows = 20): array
    {

        $response = [];
        $model = new Model();
        $model = $model->order('create_time', 'desc')
            ->page($page, $rows);
        if (is_array($param)) {
            $model = $model->where($param);
        }
        $rows = $model->select();
        if (!is_null($rows)) {
            $response = $rows->toArray();
            foreach ($response as &$vo) {
                try {
                    $vo['complete'] = $this->template_replace($vo['template_id'], $vo['content']);
                } catch (MessageException $exception) {
                    $vo['complete'] = '';
                }

            }
        }
        return $response;
    }

}