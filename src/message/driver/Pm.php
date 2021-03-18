<?php


namespace uukule\message\driver;


use EasyWeChat\Kernel\Exceptions\InvalidArgumentException;
use think\facade\Cache;
use uukule\message\core\Data;
use uukule\message\core\Model;
use uukule\message\core\Queue;
use uukule\MessageAbstract;
use AlibabaCloud\Client\AlibabaCloud;
use AlibabaCloud\Client\Exception\ClientException;
use AlibabaCloud\Client\Exception\ServerException;
use uukule\MessageException;

/**
 * @method MessageAbstract url(string $url, string $appid)
 * @method MessageAbstract access_token(string $access_token)
 */
class Pm extends MessageAbstract
{
    protected $config = [
        'platform_id' => 3,//平台ID
        'table' => 'message',
        'group_name' => 'message',
        'is_queue' => true, //是否队列发送
    ];



    protected $fromuser = '';
    protected $subject = '';


    public function __construct(array $config = [])
    {
        $this->config = array_merge($this->config, $config, config('message.database'));

    }

    /**
     * 使用队列发送
     * @param bool $is_queue
     * @return MessageAbstract
     */
    public function queue(bool $is_queue): MessageAbstract
    {
        $this->config['is_queue'] = $is_queue;
        return $this;
    }



    /**
     * 消息主题
     * @param string $subject
     * @return MessageAbstract
     */
    public function subject(string $subject): MessageAbstract
    {
        $this->subject = $subject;
        return $this;
    }

    /**
     * 发送内容
     * @param array|string $data
     * @return MessageAbstract
     */
    public function content($data): MessageAbstract
    {
        $this->content = $data;
        return $this;
    }

    /**
     * 发送
     * @return array
     */
    public function send(): array
    {
        $response = [];
        $model = new Model();
        $model->data([
            'g_msgid' => session_create_id(),
            'appid' => $this->config['group_name'],
            'platfrom_id' => $this->config['platfrom_id'],
            'fromuser' => $this->from_user_sign,
            'fromuser_name' => $this->from_user_name,
            'touser' => $this->touser,
            'subject' => $this->subject,
            'push_time' => date('Y-m-d H:i:s', $this->push_time ?? time()),
            'status' => MESSAGE_STATUS_COMPLETE,
        ]);
        $model->content = $this->content;
        $model->save();
        $response['g_msgid'] = $model->g_msgid;
        return $response;
    }

    /**
     * 队列发送
     * @param array $param
     * @return bool
     */
    public function queueSend(string $msgid, array $query): bool
    {
        return true;
    }

    /**
     * 数据验证
     * @return bool
     */
    public function check(): bool
    {
        // TODO: Implement check() method.
    }


    /**
     * 获取消息详情
     * @param string| $info
     * @return array
     */
    public function info(string $info): array
    {

    }

    /**
     * 组消息列表
     * @param array $param 条件
     * @param int $page
     * @param int $rows
     * @return array
     */
    public function groupMsgList($param = [], int $page = 1, int $rows = 20): array
    {
        $response = [];
        $model = new Model();
        $model = $model->where('platfrom_id', $this->config['platfrom_id'])
            ->order('create_time', 'desc')
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

    /**
     * 用户消息列表
     * @param array $param 条件
     * @param int $page
     * @param int $rows
     * @return array
     */
    public function userMsgList($param, int $page=1, int $rows = 30): array
    {
        $where = [
            'platfrom_id' => $this->config['platfrom_id']
        ];
        $where = array_merge($where, $param);

        $model = new Model();
        $response = $model->userMsgList($where, $page, $rows);
        return $response;
    }

    public function setRead(string $msgid) : bool
    {
        $model = new Model();
        $model->detailUpdate($msgid, ['read_time' => date('Y-m-d H:i:s')]);
        return true;
    }


    /**
     * 回调处理
     * @param array $param
     * @return mixed
     */
    public function callback(array $param)
    {
        return $param;
    }


}