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
 *
 * 使用示例：
 * ```php
 * $manager = new BotManager();
 *
 * $salesBot = $manager->addBot('sales', [
 *     'bot_id' => 'sales-bot-id',
 *     'secret' => 'sales-bot-secret',
 * ]);
 * $salesBot->onMessage(function ($message, $reply) {
 *     $reply->text('销售部机器人收到');
 * });
 *
 * $financeBot = $manager->addBot('finance', [
 *     'bot_id' => 'finance-bot-id',
 *     'secret' => 'finance-bot-secret',
 * ]);
 * $financeBot->onMessage(function ($message, $reply) {
 *     $reply->text('财务部机器人收到');
 * });
 *
 * $manager->start();
 * ```
 */
class BotManager
{
    /** @var array<string, WeComBot> 已注册的机器人实例 */
    private array $bots = [];

    private LoggerInterface $logger;

    public function __construct(?LoggerInterface $logger = null)
    {
        $this->logger = $logger ?? new ConsoleLogger();
    }

    /**
     * 注册一个机器人
     *
     * @param string $name   机器人名称（用于标识和检索）
     * @param array  $config 配置项（同 WeComBot 构造函数）
     * @return WeComBot 返回机器人实例，可继续注册回调
     *
     * @throws \InvalidArgumentException 名称重复或配置无效时
     */
    public function addBot(string $name, array $config): WeComBot
    {
        if (isset($this->bots[$name])) {
            throw new \InvalidArgumentException("Bot \"{$name}\" already registered");
        }

        // 如果没有指定 logger，注入带前缀的 logger 方便区分日志来源
        if (!isset($config['logger'])) {
            $config['logger'] = new PrefixLogger($this->logger, "[{$name}] ");
        }

        $bot = new WeComBot($config);
        $this->bots[$name] = $bot;

        $this->logger->info("Bot \"{$name}\" registered (bot_id={$config['bot_id']})");

        return $bot;
    }

    /**
     * 获取已注册的机器人实例
     *
     * @param string $name 机器人名称
     * @return WeComBot|null 不存在时返回 null
     */
    public function getBot(string $name): ?WeComBot
    {
        return $this->bots[$name] ?? null;
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
    public function removeBot(string $name): void
    {
        if (isset($this->bots[$name])) {
            $this->bots[$name]->disconnectOnly();
            unset($this->bots[$name]);
            $this->logger->info("Bot \"{$name}\" removed");
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

            foreach ($this->bots as $name => $bot) {
                try {
                    $bot->connectOnly();
                } catch (\Throwable $e) {
                    $this->logger->error("Failed to connect bot \"{$name}\": {$e->getMessage()}");
                }
            }
        };

        $worker->onWorkerStop = function () {
            $this->logger->info('Worker stopping, disconnecting all bots...');

            foreach ($this->bots as $name => $bot) {
                try {
                    $bot->disconnectOnly();
                } catch (\Throwable $e) {
                    $this->logger->error("Error disconnecting bot \"{$name}\": {$e->getMessage()}");
                }
            }
        };

        Worker::runAll();
    }
}
