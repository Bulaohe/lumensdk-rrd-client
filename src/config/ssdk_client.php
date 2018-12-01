<?php

return [
    //统一网关
    'gateway' => env('SSDK_CLIENT_GATEWAY', ''),

    //请求尝试次数,默认2次,不超过3次
    'try_times' => env('SSDK_CLIENT_TRY_TIMES', 2),

    //启用客户端负载均衡
    'client_load_balance' => env('SSDK_CLIENT_CLIENT_LOAD_BALANCE', 0),

    //最大轮询次数
    'max_polling' => env('SSDK_CLIENT_MAX_POLLING', 10000000),

    //默认初始节点id
    'default_target_id' => env('SSDK_CLIENT_DEFAULT_TARGET_ID', 1),

    //Redis配置
    'redis' => [
        'connection' => env('SSDK_CLIENT_REDIS_CONNECTION', 'ssdk_client'),
        'options' => [
            'host'     => env('REDIS_HOST_SSDK_CLIENT', '127.0.0.1'),
            'port'     => env('REDIS_PORT_SSDK_CLIENT', 6379),
            'database' => env('REDIS_DATABASE_SSDK_CLIENT', 0),
        ],
    ],

    //日志目录
    'log_path' => env('SSDK_CLIENT_LOG_PATH', '/data/nginx_log/job'),

    //日志名称
    'log_name' => env('SSDK_CLIENT_LOG_NAME', 'service_client'),

    //日志开关，默认打开
    'log_switch' => env('SSDK_CLIENT_LOG_SWITCH', 1),
];
