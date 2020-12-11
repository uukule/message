<?php


namespace uukule\message\driver;


use EasyWeChat\Kernel\Exceptions\InvalidArgumentException;
use think\facade\Cache;
use uukule\message\core\Data;
use uukule\message\core\Model;
use uukule\MessageException;
use uukule\MessageAbstract;
use EasyWeChat\Factory;
use uukule\message\core\Queue;
use Exception;

class WechatTemplate extends MessageAbstract
{
    /**
     * 配置
     * @var array
     */
    protected $config = [
        'platfrom_id' => 1,
        'app_id' => '',
        'secret' => '',
        'token' => '',//如：oss-cn-hangzhou.aliyuncs.com
        'encoding_aes_key' => '',//
        'auto_access_token' => false, //是否自动获取并缓存access_token
        'is_queue' => true,
        'response_type' => 'array',
        'log' => [
            'file' => '/wechat.log'
        ]
    ];

    protected $templates = [];
    /**
     * 实例
     * @var \EasyWeChat\OfficialAccount\Application
     */
    protected $app;

    protected $data = [
        'touser' => null,//接收者 user-openid
        'template_id' => null, //模板ID template-id
        //'url' => '', 跳转地址 URL
//        'miniprogram' => [ 小程序跳转
//            'appid' => '',
//            'pagepath' => '',
//        ],
        //参数  ['key1' => 'VALUE','key2' => 'VALUE']
        'data' => null,
    ];
    /**
     * 发送者
     * @var string
     */
    protected $fromuser = '';

    /**
     * 凭证
     * @var string
     */
    protected $access_token = null;

    protected $subject = '';

    //public $return = ['err_code' => 1];

    public function __construct(array $config = [])
    {
        $config = array_merge($this->config, $config, config('message.database'));
        $this->config = $config;
        try {
            $this->app = Factory::officialAccount($config);
        } catch (\think\Exception $e) {
            throw new Exception($e->getMessage(), $e->getCode());
        }

    }


    public function access_token(string $access_token): MessageAbstract
    {
        $this->app['access_token'] = $access_token;
        return $this;
    }

    /**
     * 设置模板ID
     * @param string $id
     * @return MessageAbstract
     */
    public function template_id(string $id): MessageAbstract
    {
        $this->data['template_id'] = $id;
        return $this;
    }

    /**
     * 设置跳转地址
     * @param string $url 要跳的链接地址或小程序路径
     * @param string|null $appid 小程序APPID
     * @return MessageAbstract
     */
    public function url(string $url, string $appid = null): MessageAbstract
    {

        if (is_null($appid)) {
            //跳转链接地址
            $this->data['url'] = $url;
        } else {
            //跳转到小程序
            $this->data['miniprogram'] = [
                'appid' => $appid,
                'pagepath' => $url,
            ];
        }
        return $this;
    }


    /**
     * 发送者
     * @param string $user_sign
     * @return MessageAbstract
     */
    public function fromuser(string $user_sign): MessageAbstract
    {
        $this->fromuser = $user_sign;
        return $this;
    }

    /**
     * 发送内容
     * @param array|string $data
     * @return MessageAbstract
     */
    public function content($data): MessageAbstract
    {
        $this->data['data'] = $data;
        return $this;
    }

    public function check(): bool
    {
        if (is_null($this->data['touser']))
            throw new MessageException('touser cannot be null', '10010');
        if (is_null($this->data['template_id']))
            throw new MessageException('template_id cannot be null', '10011');
        if (is_null($this->data['data']))
            throw new MessageException('template_id cannot be null', '10011');
        return true;
    }


    /**
     * 队列发送
     * @param array $param
     * @return bool
     * @throws \EasyWeChat\Kernel\Exceptions\InvalidArgumentException
     * @throws \EasyWeChat\Kernel\Exceptions\InvalidConfigException
     */
    public function queueSend(string $msgid, array $param): bool
    {

        $updateData = [];
        $updateData['send_time'] = date('Y-m-d H:i:s');
        try {
            $re = $this->app->template_message->send($param);
            if (0 === $re['errcode']) {
                $updateData['status'] = 3;
            } else {
                $updateData['status'] = 2;
                $updateData['err_message'] = "Error:{$re['errcode']} - {$re['errmsg']}";
            }
        } catch (InvalidArgumentException $exception) {
            $updateData['status'] = 2;
            $updateData['err_message'] = "Error:{$exception->getCode()} - {$exception->getMessage()}";
        }
        $model = new Model;
        $model->detailUpdate($msgid, $updateData);
        return true;
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
            'appid' => $this->config['app_id'],
            'platfrom_id' => 1,
            'fromuser' => $this->fromuser,
            'touser' => $this->touser,
            'subject' => $this->subject,
            'template_id' => $this->data['template_id'],
            'push_time' => date('Y-m-d H:i:s', $this->push_time ?? time()),
            'status' => 0,
        ]);
        $model->content = $this->data['data'];
        $model->save();
        if (!$this->config['is_queue'])
        {
            try {
                $tempData = $this->data;
                foreach ($model->sub as $sub) {
                    $tempData['touser'] = $sub['touser'];
                    $re = $this->app->template_message->send($tempData);
                    $updateData = [];
                    $updateData['send_time'] = date('Y-m-d H:i:s');
                    if (0 === $re['errcode']) {
                        $updateData['status'] = 1;
                    } else {
                        $updateData['status'] = 2;
                        $updateData['err_message'] = "Error:{$re['errcode']} - {$re['errmsg']}";
                    }
                    $model->detailUpdate($sub['msgid'], $updateData);
                }

            } catch (\think\Exception $exception) {
                $db_data = [
                    'err_message' => $exception->getMessage(),
                    'status' => 2
                ];
                $model->save($db_data);
                throw new MessageException($exception->getMessage());
            }
        }
        else {
            $queue = new Queue();
            $isFirstUser = true;
            $i = 1;
            $num = count($model->sub);
            foreach ($model->sub as $sub) {
                $packData = $this->data;
                $packData['touser'] = $sub['touser'];
                $pack = new Data();
                $pack->driver(self::class);
                $pack->config([
                    'type' => 'wechat_template',
                    'app_id' => $this->config['app_id'],
                    'secret' => $this->config['secret'],
                    'token' => $this->config['token'],//如：oss-cn-hangzhou.aliyuncs.com
                    'encoding_aes_key' => $this->config['encoding_aes_key'],//
                ]);
                $pack->time(time());
                $pack->groupMsgid($model->g_msgid);
                $pack->isFirstUser($isFirstUser);
                $pack->isLastUser($i === $num);
                $pack->touser($sub['touser']);
                $pack->data($packData);
                $pack->msgid($sub['msgid']);
                if (!is_null($this->push_time)) {
                    $queue->timeout($this->push_time);
                }
                $queue->push($pack);
                $isFirstUser = false;
                $i++;
            }
        }
        $response['g_msgid'] = $model->g_msgid;
        return $response;
    }

    public function get_templates(bool $reload = false)
    {
        $cache_key = "ServiceAccount:{$this->config['app_id']}:templates";
        $templates = Cache::get($cache_key);
        if (empty($templates) || $reload) {
            $templates = [];
            $rows = $this->app->template_message->getPrivateTemplates()['template_list'];
            foreach ($rows as $row) {
                $vo = $row;
                preg_match_all('/(\{\{)([a-z]+)(\.DATA\}\})/', $row['content'], $matchs);
                $vo['param'] = [$matchs[0], $matchs[2]];
                $templates[$vo['template_id']] = $vo;
            }
            Cache::set($cache_key, $templates, 0);
        }
        return $this->templates = $templates;
    }

    /**
     * 使用队列发送
     * @param bool $is_queue
     * @return MessageAbstract
     */
    public function queue(bool $is_queue = true): MessageAbstract
    {
        $this->config['is_queue'] = $is_queue;
        return $this;
    }

    public function info(string $msgid): array
    {
        $row = Model::find($msgid);
        $response = $row->toArray();
        $response['complete'] = $this->template_replace($row->template_id, $response['content']);
        return $response;
    }

    private function template_replace(string $template_id, array $data): string
    {
        $templates = $this->get_templates();
        $template = $templates[$template_id] ?? null;
        if (empty($template)) {
            throw new MessageException('模板不存在或已删除！');
        }
        $key = array_keys($data);
        $key = array_map(function ($vo) {
            return '{{' . $vo . '.DATA}}';
        }, $key);
        return str_replace($key, array_values($data), $template['content']);
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
        $model = $model->where('platfrom_id', 1)
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
     * 消息列表
     * @param array|string $param
     * @param int $page
     * @param int $rows
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function list($param, int $page = 1, int $rows = 20): array
    {
        $response = [];
        $model = new Model();
        $model = $model->where('platfrom_id', 1)
            ->order('create_time', 'desc')
            ->page($page, $rows);
        if (is_array($param)) {
            $model = $model->where($param);
        } elseif (is_string($param)) {
            $model->where('touser', $param);
        }
        $rows = $model->select();
        if (!is_null($rows)) {
            $response = $rows->toArray();
            foreach ($response as &$vo) {
                $vo['complete'] = $this->template_replace($vo['template_id'], $vo['content']);
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
    public function userMsgList($param = [], int $page = 1, int $rows = 30): array
    {
        $where = [
            'platfrom_id' => $this->config['platfrom_id']
        ];
        $where = array_merge($where, $param);

        $model = new Model();
        $response = $model->userMsgList($where, $page, $rows);
        return $response;
    }

    /**
     * 回调处理
     * @param array $param
     * @return mixed
     */
    public function callback(array $param)
    {
        // TODO: Implement callback() method.
    }
}