<?php

declare(strict_types=1);

namespace WeComAiBot;

use Workerman\Worker;
use WeComAiBot\Support\ConsoleLogger;
use WeComAiBot\Support\LoggerInterface;
use WeComAiBot\Support\PrefixLogger;

/**
 * 多机器人实例管理器
 *
 * 在同一个 Workerman Worker 进程中运行多个机器人，
 * 每个机器人拥有独立的 WebSocket 连接、心跳、重连和发送队列，数据完全隔离。
 * 消息和事件通过 Message::$botId / Event::$botId 标识来源机器人。
 *
 * 使用示例：
 * ```php
 * $manager = new BotManager([
 *     ['bot_id' => 'sales-bot-id', 'secret' => 'sales-secret'],
 *     ['bot_id' => 'finance-bot-id', 'secret' => 'finance-secret'],
 * ]);
 *
 * // 共享 handler：通过 $message->botId 区分来源
 * $sharedHandler = function (Message $msg, Reply $reply) {
 *     $reply->text("Bot {$msg->botId} 收到: {$msg->text}");
 * };
 *
 * $manager->getBot('sales-bot-id')->onMessage($sharedHandler);
 * $manager->getBot('finance-bot-id')->onMessage($sharedHandler);
 *
 * $manager->start();
 * ```
 */
class BotManager
{
    /** @var array<string, WeComBot> 已注册的机器人实例，key 为 bot_id */
    private array $bots = [];

    private LoggerInterface $logger;

    /**
     * @param array[]              $configs 机器人配置数组（可选），每项同 WeComBot 构造函数参数
     * @param LoggerInterface|null $logger  自定义日志
     */
    public function __construct(array $configs = [], ?LoggerInterface $logger = null)
    {
        $this->logger = $logger ?? new ConsoleLogger();

        foreach ($configs as $config) {
            $this->addBot($config);
        }
    }

    /**
     * 注册一个机器人
     *
     * @param array $config 配置项（同 WeComBot 构造函数），必须包含 bot_id 和 secret
     * @return WeComBot 返回机器人实例，可继续注册回调
     *
     * @throws \InvalidArgumentException bot_id 重复或配置无效时
     */
    public function addBot(array $config): WeComBot
    {
        $botId = $config['bot_id'] ?? '';

        if ($botId === '') {
            throw new \InvalidArgumentException('config "bot_id" is required');
        }

        if (isset($this->bots[$botId])) {
            throw new \InvalidArgumentException("Bot \"{$botId}\" already registered");
        }

        // 如果没有指定 logger，注入带前缀的 logger 方便区分日志来源
        if (!isset($config['logger'])) {
            $config['logger'] = new PrefixLogger($this->logger, "[{$botId}] ");
        }

        $bot = new WeComBot($config);
        $this->bots[$botId] = $bot;

        $this->logger->info("Bot \"{$botId}\" registered");

        return $bot;
    }

    /**
     * 获取已注册的机器人实例
     *
     * @param string $botId 机器人 ID
     * @return WeComBot|null 不存在时返回 null
     */
    public function getBot(string $botId): ?WeComBot
    {
        return $this->bots[$botId] ?? null;
    }

    /**
     * 获取所有已注册的机器人
     *
     * @return array<string, WeComBot>
     */
    public function getAllBots(): array
    {
        return $this->bots;
    }

    /**
     * 移除一个机器人（如果已连接会先断开）
     */
    public function removeBot(string $botId): void
    {
        if (isset($this->bots[$botId])) {
            $this->bots[$botId]->disconnectOnly();
            unset($this->bots[$botId]);
            $this->logger->info("Bot \"{$botId}\" removed");
        }
    }

    /**
     * 启动所有机器人（阻塞运行）
     *
     * 创建一个 Workerman Worker，所有机器人在同一个事件循环中运行，
     * 但各自拥有独立的 WebSocket 连接和状态。
     */
    public function start(): void
    {
        if (empty($this->bots)) {
            $this->logger->error('No bots registered, nothing to start');
            return;
        }

        $worker = new Worker();
        $worker->name = 'WeComAiBotManager';
        $worker->count = 1;

        $worker->onWorkerStart = function () {
            $this->logger->info('Worker started, connecting ' . count($this->bots) . ' bot(s)...');

            foreach ($this->bots as $botId => $bot) {
                try {
                    $bot->connectOnly();
                } catch (\Throwable $e) {
                    $this->logger->error("Failed to connect bot \"{$botId}\": {$e->getMessage()}");
                }
            }
        };

        $worker->onWorkerStop = function () {
            $this->logger->info('Worker stopping, disconnecting all bots...');

            foreach ($this->bots as $botId => $bot) {
                try {
                    $bot->disconnectOnly();
                } catch (\Throwable $e) {
                    $this->logger->error("Error disconnecting bot \"{$botId}\": {$e->getMessage()}");
                }
            }
        };

        Worker::runAll();
    }
}
