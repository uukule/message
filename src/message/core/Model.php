<?php


namespace uukule\message\core;


use think\db\exception\DbException;
use think\facade\Db;
use uukule\facade\Message;
use uukule\MessageException;

class Model extends \think\Model
{
    //protected $pk = 'msgid';
    //protected $config = [];

    protected $globalScope = ['sign'];

    protected $sign_id = null;

    protected $type = [
        'content' => 'serialize'
    ];
    /**
     * 消息驱动配置集合
     * @var array
     */
    protected static $drivers = null;


    public function __construct(array $data = [], array $config = [])
    {
        $config = array_merge(config('message.database'), $config);
        $this->table = $config['table'];
        $this->sign_id = $config['sign_id'];
        parent::__construct($data);
    }

    public static function onBeforeInsert($model)
    {
        $config = config('message.database');
        $data = $model->getData();
        $data['sign_id'] = $config['sign_id'];
        $model->data($data);
        $model->startTrans();
    }

    public static function onAfterInsert($model)
    {
        try {
            $db_data = $model->getData();
            $sub = [];
            $db_data['message_id'] = $db_data['id'];
            unset($db_data['id']);
            $batchData = [];
            foreach ($db_data['touser'] as $touser) {
                $db_data['touser'] = $touser[0];
                $db_data['touser_name'] = $touser[1];
                $db_data['msgid'] = session_create_id();
                $batchData[] = $db_data;
            }
            Db::table('message_details')->strict(false)->limit(200)->insertAll($batchData);
            $model->sub = $batchData;

            $model->commit();
        } catch (DbException $exception) {
            $model->rollback();
            throw new MessageException($exception->getMessage(), $exception->getCode());
        }
    }

    public function scopeSign($query){
        $query->where('sign_id', $this->sign_id);
    }
    /**
     * 更新单条信息记录
     * @param string|array $msgid |$where
     * @param array $data
     * @return int
     * @throws DbException
     */
    public function detailUpdate($param, array $data)
    {
        $db = Db::table($this->table . '_details');
        if (is_string($param)) {
            $db = $db->where('msgid', $param);
        } else {
            $db = $db->where($param);
        }
        return $db->strict(false)->update($data);
    }

    public function groupMsg(string $g_msgid = null)
    {
        if (!is_null($g_msgid)) {
            $message_id = $this->where('g_msgid', $g_msgid)->value('message_id');
        } else {
            $message_id = $this->message_id;
        }
        return Db::table($this->table . '_details')->where('message_id', $message_id);
    }

    public function subMsgTogether(array $param = []){

        $statusCountRows = Db::table('view_message')
            ->field(['status', 'count(`status`)' => 'status_num', 'ISNULL(read_time)' => 'is_no_read'])
            ->where($param)
            ->group('status,ISNULL(read_time)')
            ->select();
        $together = [];
        $together = [
            'status' => [
                MESSAGE_STATUS_WAIT => 0,
                MESSAGE_STATUS_SENTING => 0,
                MESSAGE_STATUS_FAIL => 0,
                MESSAGE_STATUS_SUCCESS => 0,
                MESSAGE_STATUS_COMPLETE => 0,
                MESSAGE_STATUS_RESET => 0,
            ],
            'is_read' => 0,
            'is_no_read' => 0
        ];
        foreach ($statusCountRows as $statusRow) {
            $together['status'][$statusRow['status']] += $statusRow['status_num'];
            if (0 == $statusRow['is_no_read']) {
                $together['is_read'] += $statusRow['status_num'];
            } else {
                $together['is_no_read'] += $statusRow['status_num'];
            }

        }
        return $together;
    }

    /**
     * 获取用户消息列表
     * @param array $param
     * @param int $page
     * @param int $rows
     * @return \think\Collection
     * @throws DbException
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function userMsgList(array $param = [], $page = 1, $rows = 30)
    {
        if(array_key_exists('touser', $param)){
            $param['sign_id'] = $this->sign_id;
        }else{
            $param[] = ['sign_id', '=', $this->sign_id];
        }
        //---------------------- 统计开始 ------------------------------
        $statusCountRows = Db::table('view_message')
            ->field(['status', 'count(`status`)' => 'status_num', 'ISNULL(read_time)' => 'is_no_read'])
            ->where($param)
            ->group('status,ISNULL(read_time)')
            ->select();
        $together = [];
        $together = [
            'status' => [
                MESSAGE_STATUS_WAIT => 0,
                MESSAGE_STATUS_SENTING => 0,
                MESSAGE_STATUS_FAIL => 0,
                MESSAGE_STATUS_SUCCESS => 0,
                MESSAGE_STATUS_COMPLETE => 0,
                MESSAGE_STATUS_RESET => 0,
            ],
            'is_read' => 0,
            'is_no_read' => 0
        ];
        foreach ($statusCountRows as $statusRow) {
            $together['status'][$statusRow['status']] += $statusRow['status_num'];
            if (0 == $statusRow['is_no_read']) {
                $together['is_read'] += $statusRow['status_num'];
            } else {
               $together['is_no_read'] += $statusRow['status_num'];
            }

        }
        //---------------------- 统计结束 ------------------------------
        $pageParam = [
            'page' => (int)$page,
            'list_rows' => (int)$rows
        ];
        $response = Db::table('view_message')
            ->field(['msgid', 'sign_id', 'touser', 'touser_name', 'status', 'err_message', 'push_time', 'send_time', 'read_time', 'create_time', 'appid', 'fromuser', 'platfrom_id', 'template_id', 'subject', 'content', 'g_msgid'])
            ->where($param)
            ->order('push_time', 'desc')
            ->paginate($pageParam);
        if (is_null($response)) {
            $response = [];
        } elseif (is_object($response)) {
            $response = $response->toArray();
            //状态统计

            foreach ($response['data'] as &$vo) {
                $vo['content'] = unserialize($vo['content']);
            }
        }
        $response['together'] = $together;
        return $response;
    }

    public function getCompleteContentAttr($value, $data)
    {
        $response = '';
        if (is_null(self::$drivers)) {
            $config = config('message');
            $drivers = [];
            foreach ($config as $driver) {
                if (!is_array($driver) || !array_key_exists('platfrom_id', $driver)) {
                    continue;
                }
                $drivers[(string)$driver['platfrom_id']] = $driver;
            }
            self::$drivers = $drivers;
        }
        try {
            $derver = self::$drivers[$data['platfrom_id']];
            if (!empty($data['template_id'])) {
                $response = Message::init($derver)->template_replace($data['template_id'], $this->content);

            } else {
                $response = $this->content;
            }
        } catch (\Exception $exception) {
            $response = $exception->getMessage();
        }
        return $response;

    }

    public function getPlatfromDescriptionAttr($value, $data)
    {
        $response = '';
        if (is_null(self::$drivers)) {
            $config = config('message');
            $drivers = [];
            foreach ($config as $driver) {
                if (!array_key_exists('platfrom_id', $driver)) {
                    continue;
                }
                $drivers[(string)$driver['platfrom_id']] = $driver;
            }
            self::$drivers = $drivers;
        }
        try {
            $derver = self::$drivers[$data['platfrom_id']];
            return $derver['description'];
        } catch (\Exception $exception) {
            return $exception->getMessage();
        }
    }

}