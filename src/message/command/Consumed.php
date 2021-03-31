<?php


namespace uukule\message\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;
use uukule\facade\Message;
use uukule\message\core\FormatOutput;
use uukule\message\core\Queue;
use uukule\MessageInterface;
use uukule\message\core\Model;

class Consumed extends Command
{
    /**
     * @var MessageInterface
     */
    protected $handle = [];

    protected $reset_times = 0;

    /**
     * 配置指令
     */
    protected function configure()
    {
        $this->setName('message:queue')->setDescription('执行消息队列');
    }

    /**
     * 执行指令
     * @param Input $input
     * @param Output $output
     * @return null|int
     * @throws LogicException
     * @see setCode()
     */
    protected function execute(Input $input, Output $output)
    {
        $queue = new Queue();
        $model = new Model();
        try {
            while (true) {
                $data = $queue->pop();
                if (empty($data)) {
                    sleep(1);
                    FormatOutput::green('.');

                    if (time() % 60 === 0) {
                        FormatOutput::green("\n=====" . date('Y-m-d H:i:s') . "=======\n");
                    }
                    continue;
                }
                if ($data->isFirstUser()) {
                    FormatOutput::yellow("\n========================消息组({$data->groupMsgid()}) 开始发送===================================================\n");
                    Db::table('message')->where('g_msgid', $data->groupMsgid())->update(['status' => MESSAGE_STATUS_SENTING]);
                }
                echo "\n----------------------------------------------------------------------------------------------\n";
                try {
                    $driver_name = md5(serialize($data->config()));
                    if (!array_key_exists($driver_name, $this->handle)) {
                        $driver_class_name = $data->driver();
                        $this->handle[$driver_name] = new $driver_class_name($data->config());
                    }
                    $handle = $this->handle[$driver_name];

                    FormatOutput::blue($data->config()['type'] . ':' . $data->appid . ':' . $data->msgid() . "\n");
                    FormatOutput::blue('提交时间: ' . date('Y-m-d H:i:s', $data->time()) . "\n");
                    FormatOutput::blue('正在发送中.... 接收者: ' . $data->touser() . "\n");
                    FormatOutput::blue('pack: ' . json_encode($data->data(), 256) . "\n");
                    $handle->queueSend($data->msgid(), $data->data());
                    FormatOutput::green('发送成功 发送时间: ' . date('Y-m-d H:i:s') . "\n");

                } catch (\Exception $exception) {
                    FormatOutput::red("Error:{$exception->getMessage()}");
                    FormatOutput::red("FILE:{$exception->getFile()}");
                    FormatOutput::red("Line:{$exception->getLine()}");
                }
                FormatOutput::yellow("\n-------------------------------------------------------------------------------------------------------------------\n");
                if ($data->isLastUser()) {
                    $sql = Db::table('message')->where('g_msgid', $data->groupMsgid())->update(['status' => MESSAGE_STATUS_COMPLETE]);
                    FormatOutput::yellow($sql);
                    FormatOutput::yellow("\n========================消息组({$data->groupMsgid()}) 结束发送===================================================\n");
                }
            }
        } catch (\RedisException $e) {
            FormatOutput::red("Error:{$exception->getMessage()}");
            FormatOutput::red("FILE:{$exception->getFile()}");
            FormatOutput::red("Line:{$exception->getLine()}");
            $this->execute($input, $output);
        }
    }

    private function driver(array $config): MessageInterface
    {
        return Message::init($config);
    }
}