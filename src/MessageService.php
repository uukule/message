<?php


namespace uukule;


use think\Service;
use uukule\message\command\Consumed;
use uukule\message\command\Timer;

class MessageService extends Service
{

    public function register()
    {
    }

    public function boot()
    {
        $this->commands(['message:queue' => Consumed::class]);
        $this->commands(['message:timer' => Timer::class]);
    }

}