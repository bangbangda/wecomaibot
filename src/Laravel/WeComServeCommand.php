<?php

declare(strict_types=1);

namespace WeComAiBot\Laravel;

use Illuminate\Console\Command;
use WeComAiBot\BotManager;
use WeComAiBot\Event\Event;
use WeComAiBot\Message\Message;
use WeComAiBot\Message\Reply;
use WeComAiBot\WeComBot;

/**
 * Laravel Artisan 命令：启动企微机器人
 *
 * 用法：php artisan wecom:serve
 *
 * 自动检测配置模式：
 * - 配置了 bots 数组 → 多机器人模式（BotManager）
 * - 仅配置 bot_id/secret → 单机器人模式（WeComBot）
 */
class WeComServeCommand extends Command
{
    protected $signature = 'wecom:serve';
    protected $description = '启动企业微信 AI 机器人 WebSocket 服务';

    public function handle(): int
    {
        $config = config('wecomaibot');
        $bots = $config['bots'] ?? [];

        if (!empty($bots)) {
            return $this->startMultiBotMode($config, $bots);
        }

        return $this->startSingleBotMode($config);
    }

    /**
     * 单机器人模式
     */
    private function startSingleBotMode(array $config): int
    {
        if (empty($config['bot_id']) || empty($config['secret'])) {
            $this->error('请先配置 WECOM_BOT_ID 和 WECOM_BOT_SECRET 环境变量');
            $this->line('');
            $this->line('在 .env 文件中添加：');
            $this->line('  WECOM_BOT_ID=your-bot-id');
            $this->line('  WECOM_BOT_SECRET=your-bot-secret');
            return self::FAILURE;
        }

        /** @var WeComBot $bot */
        $bot = app(WeComBot::class);

        $this->registerHandler($bot, $config);

        $bot->onAuthenticated(function () {
            $this->info('机器人已上线，等待消息...');
        });

        $bot->onError(function (\Throwable $e) {
            $this->error("错误: {$e->getMessage()}");
        });

        $this->info('正在连接企业微信...');
        $bot->start();

        return self::SUCCESS;
    }

    /**
     * 多机器人模式
     */
    private function startMultiBotMode(array $config, array $bots): int
    {
        /** @var BotManager $manager */
        $manager = app(BotManager::class);

        // 为每个 bot 注册 handler
        foreach ($bots as $botConfig) {
            $botId = $botConfig['bot_id'] ?? '';
            $bot = $manager->getBot($botId);

            if ($bot === null) {
                continue;
            }

            // 注册 handler（如果配置了）
            $this->registerHandler($bot, $botConfig);

            $bot->onAuthenticated(function () use ($botId) {
                $this->info("[{$botId}] 已上线，等待消息...");
            });

            $bot->onError(function (\Throwable $e) use ($botId) {
                $this->error("[{$botId}] 错误: {$e->getMessage()}");
            });
        }

        $this->info('正在连接企业微信（' . count($bots) . ' 个机器人）...');
        $manager->start();

        return self::SUCCESS;
    }

    /**
     * 注册配置文件中指定的消息处理器
     */
    private function registerHandler(WeComBot $bot, array $config): void
    {
        $handlerClass = $config['handler'] ?? null;

        if ($handlerClass === null || !class_exists($handlerClass)) {
            return;
        }

        $handler = app($handlerClass);

        // 注册消息处理方法
        if (method_exists($handler, 'onMessage')) {
            $bot->onMessage(fn(Message $m, Reply $r) => $handler->onMessage($m, $r));
        }

        if (method_exists($handler, 'onText')) {
            $bot->onText(fn(Message $m, Reply $r) => $handler->onText($m, $r));
        }

        if (method_exists($handler, 'onImage')) {
            $bot->onImage(fn(Message $m, Reply $r) => $handler->onImage($m, $r));
        }

        if (method_exists($handler, 'onVoice')) {
            $bot->onVoice(fn(Message $m, Reply $r) => $handler->onVoice($m, $r));
        }

        if (method_exists($handler, 'onFile')) {
            $bot->onFile(fn(Message $m, Reply $r) => $handler->onFile($m, $r));
        }

        // 注册事件处理方法
        if (method_exists($handler, 'onEvent')) {
            $bot->onEvent('*', fn(Event $e, Reply $r) => $handler->onEvent($e, $r));
        }
    }
}
