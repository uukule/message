<?php


namespace uukule\message\driver;


use EasyWeChat\Kernel\Exceptions\InvalidArgumentException;
use think\facade\Cache;
use think\facade\Validate;
use uukule\message\core\Data;
use uukule\message\core\Model;
use uukule\message\core\Queue;
use uukule\message\core\Touser;
use uukule\MessageAbstract;
use AlibabaCloud\Client\AlibabaCloud;
use AlibabaCloud\Client\Exception\ClientException;
use AlibabaCloud\Client\Exception\ServerException;
use uukule\MessageException;

/**
 * @method MessageAbstract url(string $url, string $appid)
 * @method MessageAbstract access_token(string $access_token)
 */
class AliyunSms extends MessageAbstract
{
    protected $config = [
        'platfrom_id' => 2,
        'accessKeyId' => '',
        'accessKeySecret' => '',
        'regionId' => '',//如：cn-hangzhou
        'signName' => '',
        'is_queue' => true, //是否队列发送
    ];


    protected $query = [
        'RegionId' => "cn-hangzhou",
        'PhoneNumbers' => "",
        'SignName' => "",
        'TemplateCode' => "",
        'TemplateParam' => "{}",
    ];

    protected $fromuser = '';
    protected $subject = '';

    /**
     * 短信列表
     * @var array
     */
    protected $templates = [];

    public function __construct(array $config = [])
    {
        $this->config = array_merge($this->config, $config, config('message.database'));
        AlibabaCloud::accessKeyClient($config['accessKeyId'], $config['accessKeySecret'])
            ->regionId($config['regionId'])
            ->asDefaultClient();
        $this->query['RegionId'] = $this->config['regionId'];
        $this->query['SignName'] = $this->config['signName'];

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
     * 获取全部模板
     * @return array|mixed|MessageAbstract
     * @throws ClientException
     * @throws ServerException
     */
    public function get_templates()
    {
        if (!empty($this->templates)) {
            return $this->templates;
        }
        $template_codes = explode(',', $this->config['template_code']);
        //先从缓存获取比对
        $cache_template = Cache::get('message:aliyun:templates');
        if (!empty($cache_template)) {
            $cache_template_codes = array_keys($cache_template);
            if (empty(array_diff($template_codes, $cache_template_codes))) {
                $this->templates = $cache_template;
                return $cache_template;
            }
        }
        //从远程拉取
        $templates = [];
        foreach ($template_codes as $template_code) {
            $result = AlibabaCloud::rpc()
                ->product('Dysmsapi')
                ->version('2017-05-25')
                ->action('QuerySmsTemplate')
                ->method('POST')
                ->host('dysmsapi.aliyuncs.com')

                ->options([
                    'query' => [
                        'RegionId' => $this->config['regionId'],
                        'TemplateCode' => $template_code
                    ]
                ])
                ->request();
            $templates[$template_code] = $result->toArray();
        }
        Cache::set('message:aliyun:templates', $templates);
        $this->templates = $templates;
        return $templates;
    }


    /**
     * 接收者
     * @param Touser $tousers
     * @return MessageAbstract
     */
    public function touser(Touser $tousers): MessageAbstract
    {
//        foreach ($tousers as list($mobile, $name)) {
//            if (!Validate::regex($mobile, 'mobile')) {
//                throw new MessageException("不合法移动号码 [{$mobile}]");
//            }
//        }
        $this->touser = $tousers;
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

    public function template_id(string $id)
    {
        $this->query['TemplateCode'] = $id;
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
        $this->query['TemplateParam'] = json_encode($data, true);
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
            'appid' => $this->config['accessKeyId'],
            'platfrom_id' => 2,
            'fromuser' => $this->from_user_sign,
            'fromuser_name' => $this->from_user_name,
            'touser' => $this->touser,
            'subject' => $this->subject,
            'template_id' => $this->query['TemplateCode'],
            'push_time' => date('Y-m-d H:i:s', $this->push_time ?? time()),
            'status' => 0,
        ]);
        $model->content = $this->content;
        $model->save();
        if (!$this->config['is_queue']) {
            foreach ($model->sub as $sub) {
                try {
                    $query = $this->query;
                    $query['PhoneNumbers'] = $sub['touser'];
                    $query['CallbackUrl'] = 'https://api.dev.zqbeike.com/api/platform/aliyun/sms.json';
                    $query['callback_url'] = 'https://api.dev.zqbeike.com/api/platform/aliyun/sms.json';
                    //$query['OutId'] = $sub['msgid'];
                    $re = AlibabaCloud::rpc()
                        ->product('Dysmsapi')
                        ->version('2017-05-25')
                        ->method('POST')
                        ->host('dysmsapi.aliyuncs.com')
                        ->action('SendSms')
                        ->options([
                            'query' => $query,
                            'callback_url' => 'https://api.dev.zqbeike.com/api/platform/aliyun/sms.json'
                        ])->request();

                    //发送返回判断
                    $updateData = [];
                    $updateData['send_time'] = date('Y-m-d H:i:s');
                    if ('OK' === $re['Code']) {
                        $updateData['status'] = 1;
                        $updateData['out_msgid'] = 'BizId:' . $re['BizId'];
                        $model->detailUpdate($sub['msgid'], $updateData);
                    } else {
                        $updateData['status'] = 3;
                        $updateData['err_message'] = "Error:{$re['Code']} - {$re['Message']}";
                    }
                    $model->detailUpdate($sub['msgid'], $updateData);
                } catch (\think\Exception $exception) {
                    $db_data = [
                        'err_message' => $exception->getMessage(),
                        'status' => 2
                    ];
                    $model->detailUpdate($sub['msgid'], $db_data);
                    throw new MessageException($exception->getMessage());
                }
            }
        } else {
            $queue = new Queue();
            $isFirstUser = true;
            $i = 1;
            $num = count($model->sub);
            foreach ($model->sub as $sub) {
                $packData = $this->query;
                $packData['PhoneNumbers'] = $sub['touser'];

                $pack = new Data();
                $pack->driver(self::class);
                $pack->config([
                    'type' => 'aliyun_sms',
                    'accessKeyId' => $this->config['accessKeyId'],
                    'accessKeySecret' => $this->config['accessKeySecret'],
                    'regionId' => $this->config['regionId'],//如：cn-hangzhou
                    'signName' => $this->config['signName'],
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
     * 队列发送
     * @param array $param
     * @return bool
     */
    public function queueSend(string $msgid, array $query): bool
    {
        $model = new Model();
        $re = AlibabaCloud::rpc()
            ->product('Dysmsapi')
            ->version('2017-05-25')
            ->method('POST')
            ->host('dysmsapi.aliyuncs.com')
            ->action('SendSms')
            ->options(['query' => $query])
            ->request();

        //发送返回判断
        $updateData = [];
        $updateData['send_time'] = date('Y-m-d H:i:s');
        if ('OK' === $re['Code']) {
            $updateData['status'] = 1;
            $updateData['out_msgid'] = 'BizId:' . $re['BizId'];
            $model->detailUpdate($msgid, $updateData);
        } else {
            $updateData['status'] = 2;
            $updateData['err_message'] = "Error:{$re['Code']} - {$re['Message']}";
        }
        $model->detailUpdate($msgid, $updateData);
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
     * 消息列表
     * @param array|string $param
     * @param int $page
     * @param int $rows
     * @return array
     */
    public function list($param, int $page, int $rows): array
    {
        // TODO: Implement list() method.
    }

    /**
     * 获取消息详情
     * @param string| $info
     * @return array
     */
    public function info(string $info): array
    {
        // TODO: Implement info() method.
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
    public function userMsgList($param, int $page = 1, int $rows = 30): array
    {
        $where = [
            'platfrom_id' => $this->config['platfrom_id']
        ];
        $where = array_merge($where, $param);

        $model = new Model();
        $response = $model->userMsgList($where, $page, $rows);
        foreach ($response as &$vo) {
            try {
                $vo['content'] = unserialize($vo['content']);
                $vo['complete'] = $this->template_replace($vo['template_id'], $vo['content']);
            } catch (MessageException $exception) {
                $vo['complete'] = '';
            }
        }
        return $response;
    }


    /**
     * 回调处理
     * @param array $param
     * @return mixed
     */
    public function callback(array $param)
    {
        $model = new Model();
        foreach ($param as $vo) {
            if ('DELIVERED' === $vo['err_code']) {
                $data = [
                    'status' => 3
                ];
            } else {
                $data = [
                    'status' => 2,
                    'err_message' => $vo['err_code']
                ];
            }
            $model->detailUpdate(['out_msgid' => 'BizId:' . $vo['biz_id']], $data);
        }
        return $param;
    }


    public function template_replace(string $template_id, array $data): string
    {
        $templates = $this->get_templates();
        $template = $templates[$template_id] ?? null;
        if (empty($template)) {
            throw new MessageException('模板不存在或已删除！');
        }
        $key = array_keys($data);
        $key = array_map(function ($vo) {
            return '${' . $vo . '}';
        }, $key);
        return str_replace($key, array_values($data), $template['TemplateContent']);
    }
}