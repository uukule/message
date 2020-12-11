<?php


namespace uukule\message\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
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
                    if ($data->isFirstUser()) {
                        $model->where('g_msgid', $data->groupMsgid())->update(['status' => 1]);
                    }
                    $handle->queueSend($data->msgid(), $data->data());
                    if ($data->isLastUser()) {
                        $model->where('g_msgid', $data->groupMsgid())->update(['status' => 4]);
                    }
                    FormatOutput::green('发送成功 发送时间: ' . date('Y-m-d H:i:s') . "\n");

                } catch (\Exception $exception) {
                    FormatOutput::red("Error:{$exception->getMessage()}");
                    FormatOutput::red("FILE:{$exception->getFile()}");
                    FormatOutput::red("Line:{$exception->getLine()}");
                }
                FormatOutput::yellow('===========================================================================' . "\n");
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