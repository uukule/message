<?php


namespace uukule\message\core;


use think\db\exception\DbException;
use think\facade\Db;
use uukule\MessageException;

class Model extends \think\Model
{
    //protected $pk = 'msgid';
    //protected $config = [];
    protected $type = [
        'content' => 'serialize'
    ];

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
            $tousers = explode(',', $db_data['touser']);
            $sub = [];
            $db_data['message_id'] = $db_data['id'];
            unset($db_data['id']);
            foreach ($tousers as $touser) {
                $db_data['touser'] = $touser;
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
     * @param string|array $msgid|$where
     * @param array $data
     * @return int
     * @throws DbException
     */
    public function detailUpdate($param, array $data)
    {
        $db = Db::table($this->table . '_details');
        if(is_string($param)){
            $db = $db->where('msgid', $param);
        }else{
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
        $response = Db::view([$this->table . '_details' => 'details'], ['msgid', 'touser', 'status', 'err_message', 'push_time', 'send_time', 'read_time', 'create_time'])
            ->view([$this->table => 'main'], ['appid', 'fromuser', 'platfrom_id', 'template_id', 'subject', 'content'], 'details.message_id=main.message_id', 'LEFT')
            ->order('details.push_time', 'DESC')
            ->where($param)
            //->fetchSql(true)
            ->page($page, $rows)
            ->select();
        if(is_null($response)){
            $response = [];
        }elseif(is_object($response)){
            $response = $response->toArray();
        }
        return $response;
    }

}