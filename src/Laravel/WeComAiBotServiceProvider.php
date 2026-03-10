<?php

declare(strict_types=1);

namespace WeComAiBot\Laravel;

use Illuminate\Support\ServiceProvider;
use WeComAiBot\WeComBot;

class WeComAiBotServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/config.php', 'wecomaibot');

        $this->app->singleton(WeComBot::class, function ($app) {
            $config = $app['config']['wecomaibot'];

            return new WeComBot([
                'bot_id' => $config['bot_id'],
                'secret' => $config['secret'],
                'ws_url' => $config['ws_url'] ?? 'wss://openws.work.weixin.qq.com',
                'heartbeat_interval' => $config['heartbeat_interval'] ?? 30,
                'max_reconnect_attempts' => $config['max_reconnect_attempts'] ?? 100,
            ]);
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            // 发布配置文件
            $this->publishes([
                __DIR__ . '/config.php' => config_path('wecomaibot.php'),
            ], 'wecomaibot-config');

            // 注册 Artisan 命令
            $this->commands([
                WeComServeCommand::class,
            ]);
        }
    }
}
