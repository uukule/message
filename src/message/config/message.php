<?php

return [
    'database' => [
        'table' => 'message',
        'is_queue' => true
    ],
    //启用定时任务时需要开启REDIS服务
    'redis' => [
        // 服务器地址
        'host' => env('cache.host', ''),
        'port' => env('cache.port', 6379),
        'password' => env('cache.password', '')
    ],
    'local' => [
        'type' => 'local',
        'platfrom_id' => 3
    ],
    'sms' => [
        'platfrom_id' => 2,
        'type' => 'aliyun_sms',
        'accessKeyId' => '',
        'accessKeySecret' => '',
        'regionId' => 'cn-hangzhou',//如：cn-hangzhou
        'signName' => '',//短信签名
        'template_code' => 'SMS_203075423,SMS_203075422,SMS_203075421,SMS_203075420,SMS_203075419,SMS_203075418'
    ],
    'wechat' => [

    ]
];