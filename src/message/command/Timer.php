<?php


namespace uukule\message\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\db\exception\PDOException;
use think\Exception;
use think\exception\ErrorException;
use uukule\facade\Message;
use uukule\message\core\FormatOutput;
use uukule\message\core\Queue;
use uukule\MessageInterface;
use uukule\message\core\Model;

class Timer extends Command
{
    /**
     * @var MessageInterface
     */
    protected $handle = [];


    /**
     * 配置指令
     */
    protected function configure()
    {
        $this->setName('message:timer')->setDescription('消息定时器');
    }

    /**
     * 执行指令
     * @param Input  $input
     * @param Output $output
     * @return null|int
     * @throws LogicException
     * @see setCode()
     */
    protected function execute(Input $input, Output $output)
    {
        try{
            $queue = new Queue();
            $queue->timer();
        }catch (\RedisException $exception){
            FormatOutput::red("Error:{$exception->getMessage()}");
            FormatOutput::red("FILE:{$exception->getFile()}");
            FormatOutput::red("Line:{$exception->getLine()}");
            $this->execute($input, $output);
        }catch (\Exception $exception){
            FormatOutput::red("Error:{$exception->getMessage()}");
            FormatOutput::red("FILE:{$exception->getFile()}");
            FormatOutput::red("Line:{$exception->getLine()}");
            $this->execute($input, $output);
        }
    }

    private function driver(array $config) : MessageInterface
    {
        return Message::init($config);
    }
}