<?php

namespace Ssdk\Client;

use Illuminate\Support\ServiceProvider;

class ClientServiceProvider extends ServiceProvider
{
    public function register()
    {
        $config = config('ssdk_client');

        //注入Client
        $this->app->singleton(Client::class, function() use ($config) {
            return new Client($config);
        });

        //注入Load Balance
        $this->app->singleton(LoadBalance::class, function() use ($config) {
            return new LoadBalance($config);
        });

        //注入日志组件
        $this->app->singleton(Log::class, function () use ($config) {
            return new Log($config);
        });

        //注入redis配置
        config(['database.redis.' . $config['redis']['connection'] => $config['redis']['options']]);
    }

    public function boot()
    {
        //发布配置文件
        $this->publishes([
            __DIR__ . '/config/ssdk_client.php' => base_path('config/ssdk_client.php'),
        ]);
    }
}
