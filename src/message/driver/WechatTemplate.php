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
        'token' => '',
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
        'data' => null,
    ];
    /**
     * 发送者
     * @var string
     */
    protected $fromuser = '';

    protected $subject = '';

    //public $return = ['err_code' => 1];

    public function __construct(array $config = [])
    {
        $config = array_merge($this->config, $config);
        $this->config = $config;
        try {
            $this->app = Factory::officialAccount($config);
        } catch (\think\Exception $e) {
            throw new Exception($e->getMessage(), $e->getCode());
        }

    }


    /**
     * 设置token
     *
     * @param string|null $access_token
     * @return MessageAbstract
     * @throws InvalidArgumentException
     * @throws \EasyWeChat\Kernel\Exceptions\HttpException
     * @throws \EasyWeChat\Kernel\Exceptions\InvalidConfigException
     * @throws \EasyWeChat\Kernel\Exceptions\RuntimeException
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function access_token(string $access_token = null): MessageAbstract
    {
        if (is_null($access_token)) {
            $access_token_key = "wechat:{$this->config['app_id']}:access_token";
            $redis = (new Queue())->redis();
            if (!$redis->exists($access_token_key)) {
                $access_token = $this->app->access_token->getToken(true)['access_token']; // 强制重新从微信服务器获取 token.
                $redis->set($access_token_key, $access_token);
                $redis->expire($access_token_key, 7100);
            } else {
                $access_token = $redis->get($access_token_key);
            }
        }
        $this->app['access_token']->setToken($access_token);
        $this->is_set_access_token = true;
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
            throw new MessageException('touser cannot be null', MESSAGE_TOUSER_NO_EXISTENT);
        if (is_null($this->data['template_id']))
            throw new MessageException('template_id cannot be null', MESSAGE_TEMPLATE_NO_EXISTENT);
        if (is_null($this->data['data']))
            throw new MessageException('template_id cannot be null', MESSAGE_TEMPLATE_NO_EXISTENT);
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
            if(strlen($param['touser']) === 28){
                $re = $this->app->template_message->send($param);
                if (0 === $re['errcode']) {
                    $updateData['status'] = MESSAGE_STATUS_SUCCESS;
                } else {
                    $updateData['status'] = MESSAGE_STATUS_FAIL;
                    switch ($re['errcode']) {
                        case '40003':
                            $updateData['err_message'] = '用户未绑定公众号！';
                            break;
                        case '43004':
                            $updateData['err_message'] = '用户未关注公众号！';
                            break;
                        default:
                            $updateData['err_message'] = "Error:{$re['errcode']} - {$re['errmsg']}";
                            break;
                    }
                }
            }else{
                $updateData['status'] = MESSAGE_STATUS_FAIL;
                $updateData['err_message'] = '用户未绑定公众号！';
            }
        } catch (InvalidArgumentException $exception) {
            $updateData['status'] = MESSAGE_STATUS_FAIL;
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
            'fromuser' => $this->from_user_sign,
            'fromuser_name' => $this->from_user_name,
            'touser' => $this->touser,
            'subject' => $this->subject,
            'template_id' => $this->data['template_id'],
            'push_time' => date('Y-m-d H:i:s', $this->push_time ?? time()),
            'status' => MESSAGE_STATUS_WAIT,
        ]);
        $model->content = $this->data['data'];
        $model->save();
        if (!$this->config['is_queue']) {
            $model->save(['status' => MESSAGE_STATUS_SENTING]);
            try {
                $tempData = $this->data;
                foreach ($model->sub as $sub) {
                    $tempData['touser'] = $sub['touser'];
                    $updateData = [];
                    $updateData['send_time'] = date('Y-m-d H:i:s');
                    if(strlen($tempData['touser']) === 28){
                        $re = $this->app->template_message->send($tempData);
                        if (0 === $re['errcode']) {
                            $updateData['status'] = MESSAGE_STATUS_SUCCESS;
                        } else {
                            $updateData['status'] = MESSAGE_STATUS_FAIL;
                            switch ($re['errcode']) {
                                case '40003':
                                    $updateData['err_message'] = '用户未绑定公众号！';
                                    break;
                                case '43004':
                                    $updateData['err_message'] = '用户未关注公众号！';
                                    break;
                                default:
                                    $updateData['err_message'] = "Error:{$re['errcode']} - {$re['errmsg']}";
                                    break;
                            }
                        }
                    }else{
                        $updateData['status'] = MESSAGE_STATUS_FAIL;
                        $updateData['err_message'] = '用户未绑定公众号！';
                    }
                    $model->detailUpdate($sub['msgid'], $updateData);
                }
                //发送结束
                $model->save(['status' => MESSAGE_STATUS_COMPLETE]);
            } catch (\think\Exception $exception) {
                $db_data = [
                    'err_message' => $exception->getMessage(),
                    'status' => MESSAGE_STATUS_FAIL
                ];
                $model->save($db_data);
                throw new MessageException($exception->getMessage());
            }
        } else {
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


    /**
     * 获取所有模板列表
     * @param bool $reload
     * @return array|mixed|MessageAbstract
     * @throws \EasyWeChat\Kernel\Exceptions\InvalidConfigException
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getTemplates(bool $reload = false): array
    {
        $cache_key = "wechat:{$this->config['app_id']}:templates";
        $redis = (new Queue())->redis($cache_key);
        $is_exists = $redis->exists();
        $reload = true;
        if (!$is_exists || $reload) {
            $templates = [];
            $sourceTemplatesData = $this->app->template_message->getPrivateTemplates();
            if (!empty($sourceTemplatesData['errcode'])) {
                throw new MessageException($sourceTemplatesData['errmsg'], $sourceTemplatesData['errcode']);
            }
            foreach ($sourceTemplatesData['template_list'] as $row) {
                $vo = $row;
                preg_match_all('/(\{\{)([a-z0-9]+)(\.DATA\}\})/', $row['content'], $matchs);
                $vo['param'] = [$matchs[0], $matchs[2]];
                $templates[$vo['template_id']] = $vo;
            }
            $redis->set($cache_key, json_encode($templates, 256));
        } else {
            $templates = json_decode($redis->get($cache_key), true);
        }
        return $this->templates = $templates;
    }

    /**
     * 添加模板
     * 在公众号后台获取 $shortId 并添加到账户。
     * @param string $shortId
     * @return string $template_id
     * @throws MessageException
     * @throws \EasyWeChat\Kernel\Exceptions\InvalidConfigException
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function addTemplates(string $shortId): string
    {
        $template_id = null;
        $result = $this->app->template_message->addTemplate($shortId);
        if (0 !== $result['errcode']) {
            throw new MessageException($result['errmsg']);
        }
        $template_id = $result['template_id'];
        return $template_id;
    }


    /**
     * 删除模板
     * @param string $template_id
     * @return bool
     * @throws MessageException
     * @throws \EasyWeChat\Kernel\Exceptions\InvalidConfigException
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function deleteTemplates(string $template_id): bool
    {
        $result = $this->app->template_message->deletePrivateTemplate($template_id);
        if (0 !== $result['errcode']) {
            throw new MessageException($result['errmsg']);
        }
        return true;
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

    public function template_replace(string $template_id, array $data): string
    {
        $templates = $this->getTemplates();
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