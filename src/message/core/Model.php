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
    protected $type = [
        'content' => 'serialize'
    ];
    /**
     * 消息驱动配置集合
     * @var array
     */
    protected static $drivers = null;

    public function __construct(array $data = [])
    {
        $config = config('message.database');
        $this->table = $config['table'];
        parent::__construct($data);
    }

    public static function onBeforeInsert($model)
    {
        $model->startTrans();
    }

    public static function onAfterInsert($model)
    {
        try {
            $db_data = $model->getData();
            $sub = [];
            $db_data['message_id'] = $db_data['id'];
            unset($db_data['id']);
            foreach ($db_data['touser'] as $touser) {
                $db_data['touser'] = $touser[0];
                $db_data['touser_name'] = $touser[1];
                $db_data['msgid'] = session_create_id();
                $sub[] = $db_data;
                Db::table('message_details')->strict(false)->insert($db_data);
            }
            $model->sub = $sub;

            $model->commit();
        } catch (DbException $exception) {
            $model->rollback();
            throw new MessageException($exception->getMessage(), $exception->getCode());
        }
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
    public function userMsgList($param = [], $page = 1, $rows = 30)
    {
        $response = Db::view([$this->table . '_details' => 'details'], ['msgid', 'touser', 'touser_name', 'status', 'err_message', 'push_time', 'send_time', 'read_time', 'create_time'])
            ->view([$this->table => 'main'], ['appid', 'fromuser', 'platfrom_id', 'template_id', 'subject', 'content', 'g_msgid'], 'details.message_id=main.message_id', 'LEFT')
            ->order('details.create_time', 'DESC')
            ->where($param)
            //->fetchSql(true)
            ->page($page, $rows)
            ->paginate();
        if (is_null($response)) {
            $response = [];
        } elseif (is_object($response)) {
            $response = $response->toArray();
            foreach ($response['data'] as &$vo){
                $vo['content'] = unserialize($vo['content']);
            }
        }
        return $response;
    }

    public function getCompleteContentAttr($value, $data)
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
        try{
            $derver = self::$drivers[$data['platfrom_id']];
            if(!empty($data['template_id'])){
                $response = Message::init($derver)->template_replace($data['template_id'],$this->content);

            }else{
                $response = $this->content;
            }
        }catch (\Exception $exception){
            $response = $exception->getMessage();
        }
        return $response;

    }

    public function getPlatfromDescriptionAttr($value, $data){
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
        try{
            $derver = self::$drivers[$data['platfrom_id']];
            return $derver['description'];
        }catch (\Exception $exception){
            return $exception->getMessage();
        }
    }

}