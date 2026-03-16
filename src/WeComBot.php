<?php

declare(strict_types=1);

namespace WeComAiBot;

use Workerman\Worker;
use WeComAiBot\Connection\WsClient;
use WeComAiBot\Event\Event;
use WeComAiBot\Message\Message;
use WeComAiBot\Message\MessageParser;
use WeComAiBot\Message\Reply;
use WeComAiBot\Protocol\Command;
use WeComAiBot\Protocol\FrameBuilder;
use WeComAiBot\Media\MediaUploader;
use WeComAiBot\Support\ConsoleLogger;
use WeComAiBot\Support\LoggerInterface;

/**
 * 企业微信 AI 机器人主入口类
 *
 * 用户唯一需要接触的类，封装了 Workerman Worker 和 WsClient 的全部细节。
 *
 * 使用示例：
 * ```php
 * $bot = new WeComBot([
 *     'bot_id' => 'your-bot-id',
 *     'secret' => 'your-secret',
 * ]);
 *
 * $bot->onMessage(function (Message $message, Reply $reply) {
 *     $reply->text('你好！');
 * });
 *
 * $bot->start();
 * ```
 */
class WeComBot
{
    /** 默认 WebSocket 地址 */
    private const DEFAULT_WS_URL = 'wss://openws.work.weixin.qq.com';

    private string $botId;
    private string $secret;
    private string $wsUrl;
    private int $heartbeatInterval;
    private int $maxReconnectAttempts;
    private int $ackTimeout;
    private LoggerInterface $logger;

    private ?WsClient $client = null;

    /** @var array<string, true> 已处理的 msgid 集合（用于去重） */
    private array $processedMsgIds = [];

    /** 去重缓存最大容量 */
    private const DEDUP_MAX_SIZE = 1000;

    /** @var array<string, list<callable>> 消息回调（按类型分组） */
    private array $messageHandlers = [];

    /** @var array<string, list<callable>> 事件回调（按类型分组） */
    private array $eventHandlers = [];

    /** @var callable|null 认证成功回调 */
    private mixed $onAuthenticatedCallback = null;

    /** @var callable|null 错误回调 */
    private mixed $onErrorCallback = null;

    /**
     * @param array $config 配置项：
     *   - bot_id: string (必填) 机器人 ID
     *   - secret: string (必填) 机器人 Secret
     *   - ws_url: string (可选) WebSocket 地址，默认 wss://openws.work.weixin.qq.com
     *   - heartbeat_interval: int (可选) 心跳间隔秒数，默认 30
     *   - max_reconnect_attempts: int (可选) 最大重连次数，默认 100，-1 为无限
     *   - ack_timeout: int (可选) 发送帧 ack 超时秒数，默认 10
     *   - logger: LoggerInterface (可选) 自定义日志
     */
    public function __construct(array $config)
    {
        if (empty($config['bot_id'])) {
            throw new \InvalidArgumentException('config "bot_id" is required');
        }
        if (empty($config['secret'])) {
            throw new \InvalidArgumentException('config "secret" is required');
        }

        $this->botId = $config['bot_id'];
        $this->secret = $config['secret'];
        $this->wsUrl = $config['ws_url'] ?? self::DEFAULT_WS_URL;
        $this->heartbeatInterval = $config['heartbeat_interval'] ?? 30;
        $this->maxReconnectAttempts = $config['max_reconnect_attempts'] ?? 100;
        $this->ackTimeout = $config['ack_timeout'] ?? 10;
        $this->logger = $config['logger'] ?? new ConsoleLogger();
    }

    // ========== 消息监听 ==========

    /**
     * 监听所有类型的消息
     *
     * @param callable(Message, Reply): void $handler
     */
    public function onMessage(callable $handler): static
    {
        $this->messageHandlers['*'][] = $handler;
        return $this;
    }

    /**
     * 监听文本消息
     *
     * @param callable(Message, Reply): void $handler
     */
    public function onText(callable $handler): static
    {
        $this->messageHandlers['text'][] = $handler;
        return $this;
    }

    /**
     * 监听图片消息
     *
     * @param callable(Message, Reply): void $handler
     */
    public function onImage(callable $handler): static
    {
        $this->messageHandlers['image'][] = $handler;
        return $this;
    }

    /**
     * 监听语音消息（语音已转文字）
     *
     * @param callable(Message, Reply): void $handler
     */
    public function onVoice(callable $handler): static
    {
        $this->messageHandlers['voice'][] = $handler;
        return $this;
    }

    /**
     * 监听文件消息
     *
     * @param callable(Message, Reply): void $handler
     */
    public function onFile(callable $handler): static
    {
        $this->messageHandlers['file'][] = $handler;
        return $this;
    }

    /**
     * 监听图文混排消息
     *
     * @param callable(Message, Reply): void $handler
     */
    public function onMixed(callable $handler): static
    {
        $this->messageHandlers['mixed'][] = $handler;
        return $this;
    }

    // ========== 事件监听 ==========

    /**
     * 监听事件
     *
     * @param string $eventType 事件类型（enter_chat, template_card_event, feedback_event），或 '*' 监听全部
     * @param callable(Event, Reply): void $handler
     */
    public function onEvent(string $eventType, callable $handler): static
    {
        $this->eventHandlers[$eventType][] = $handler;
        return $this;
    }

    /**
     * 监听模板卡片点击事件
     *
     * @param callable(Event, Reply): void $handler
     */
    public function onTemplateCardEvent(callable $handler): static
    {
        return $this->onEvent('template_card_event', $handler);
    }

    // ========== 生命周期回调 ==========

    /**
     * 认证成功回调
     *
     * @param callable(): void $handler
     */
    public function onAuthenticated(callable $handler): static
    {
        $this->onAuthenticatedCallback = $handler;
        return $this;
    }

    /**
     * 错误回调
     *
     * @param callable(\Throwable): void $handler
     */
    public function onError(callable $handler): static
    {
        $this->onErrorCallback = $handler;
        return $this;
    }

    // ========== 主动操作 ==========

    /**
     * 主动推送消息给用户（单聊）
     *
     * @param string        $userId  用户 userid
     * @param string        $content Markdown 内容
     * @param callable|null $onAck   ack 回调：fn(int $errcode) => void
     */
    public function pushToUser(string $userId, string $content, ?callable $onAck = null): void
    {
        $this->push($userId, $content, 1, $onAck);
    }

    /**
     * 主动推送消息到群聊
     *
     * @param string        $chatId  群聊 chatid
     * @param string        $content Markdown 内容
     * @param callable|null $onAck   ack 回调：fn(int $errcode) => void
     */
    public function pushToGroup(string $chatId, string $content, ?callable $onAck = null): void
    {
        $this->push($chatId, $content, 2, $onAck);
    }

    /**
     * 主动向指定会话发送消息（chat_type 自动判断）
     *
     * 需要在 start() 后且认证成功后调用。
     * 建议优先使用 pushToUser() 或 pushToGroup() 明确指定会话类型。
     *
     * @param string        $chatId  会话 ID（单聊填 userid，群聊填 chatid）
     * @param string        $content Markdown 内容
     * @param callable|null $onAck   ack 回调：fn(int $errcode) => void
     */
    public function sendMessage(string $chatId, string $content, ?callable $onAck = null): void
    {
        $this->push($chatId, $content, 0, $onAck);
    }

    // ========== 模板卡片 ==========

    /**
     * 主动推送模板卡片给用户（单聊）
     *
     * @param string        $userId 用户 userid
     * @param array         $card   模板卡片结构体（透传，由调用者定义）
     * @param callable|null $onAck  ack 回调：fn(int $errcode) => void
     */
    public function pushTemplateCardToUser(string $userId, array $card, ?callable $onAck = null): void
    {
        $this->pushTemplateCard($userId, $card, 1, $onAck);
    }

    /**
     * 主动推送模板卡片到群聊
     *
     * @param string        $chatId 群聊 chatid
     * @param array         $card   模板卡片结构体（透传，由调用者定义）
     * @param callable|null $onAck  ack 回调：fn(int $errcode) => void
     */
    public function pushTemplateCardToGroup(string $chatId, array $card, ?callable $onAck = null): void
    {
        $this->pushTemplateCard($chatId, $card, 2, $onAck);
    }

    /**
     * 更新模板卡片
     *
     * 收到 template_card_event 后，5 秒内调用此方法更新卡片状态。
     *
     * @param string        $reqId  事件帧的 req_id（透传）
     * @param array         $card   更新后的模板卡片结构体
     * @param callable|null $onAck  ack 回调：fn(int $errcode) => void
     */
    public function updateTemplateCard(string $reqId, array $card, ?callable $onAck = null): void
    {
        if (!$this->client?->isConnected()) {
            $this->logger->error('Cannot update template card: not connected');
            return;
        }

        $frame = FrameBuilder::updateTemplateCard($reqId, $card);
        $this->client->sendQueued($frame, $reqId, $onAck);
        $this->logger->info("Queued template card update (req_id={$reqId})");
    }

    /**
     * 内部推送模板卡片方法
     */
    private function pushTemplateCard(string $chatId, array $card, int $chatType, ?callable $onAck = null): void
    {
        if (!$this->client?->isConnected()) {
            $this->logger->error('Cannot push template card: not connected');
            return;
        }

        $frame = FrameBuilder::sendTemplateCard($chatId, $card, $chatType);
        $decoded = json_decode($frame, true);
        $reqId = $decoded['headers']['req_id'] ?? '';

        $this->client->sendQueued($frame, $reqId, $onAck);
        $this->logger->info("Queued template card push to {$chatId} (chat_type={$chatType})");
    }

    /**
     * 内部推送方法
     *
     * @param string        $chatId   会话 ID
     * @param string        $content  Markdown 内容
     * @param int           $chatType 1=单聊，2=群聊，0=自动
     * @param callable|null $onAck    ack 回调
     */
    private function push(string $chatId, string $content, int $chatType, ?callable $onAck = null): void
    {
        if (!$this->client?->isConnected()) {
            $this->logger->error('Cannot push: not connected');
            return;
        }

        $frame = FrameBuilder::sendMessage($chatId, $content, $chatType);
        $decoded = json_decode($frame, true);
        $reqId = $decoded['headers']['req_id'] ?? '';

        $this->client->sendQueued($frame, $reqId, $onAck);
        $this->logger->info("Queued push to {$chatId} (chat_type={$chatType})");
    }

    // ========== 媒体消息推送 ==========

    /**
     * 主动推送图片给用户（单聊）
     *
     * 自动上传文件获取 media_id 后发送。
     * 支持格式：png, jpg/jpeg, gif；大小 ≤ 2MB。
     *
     * @param string        $userId   用户 userid
     * @param string        $filePath 本地图片路径
     * @param callable|null $onAck    ack 回调：fn(int $errcode) => void
     */
    public function pushImageToUser(string $userId, string $filePath, ?callable $onAck = null): void
    {
        $this->pushMedia($userId, $filePath, 'image', 1, $onAck);
    }

    /**
     * 主动推送图片到群聊
     */
    public function pushImageToGroup(string $chatId, string $filePath, ?callable $onAck = null): void
    {
        $this->pushMedia($chatId, $filePath, 'image', 2, $onAck);
    }

    /**
     * 主动推送文件给用户（单聊）
     *
     * 大小 ≤ 20MB。
     *
     * @param string        $userId   用户 userid
     * @param string        $filePath 本地文件路径
     * @param callable|null $onAck    ack 回调：fn(int $errcode) => void
     */
    public function pushFileToUser(string $userId, string $filePath, ?callable $onAck = null): void
    {
        $this->pushMedia($userId, $filePath, 'file', 1, $onAck);
    }

    /**
     * 主动推送文件到群聊
     */
    public function pushFileToGroup(string $chatId, string $filePath, ?callable $onAck = null): void
    {
        $this->pushMedia($chatId, $filePath, 'file', 2, $onAck);
    }

    /**
     * 主动推送语音给用户（单聊）
     *
     * 支持格式：amr；大小 ≤ 2MB。
     *
     * @param string        $userId   用户 userid
     * @param string        $filePath 本地语音文件路径
     * @param callable|null $onAck    ack 回调：fn(int $errcode) => void
     */
    public function pushVoiceToUser(string $userId, string $filePath, ?callable $onAck = null): void
    {
        $this->pushMedia($userId, $filePath, 'voice', 1, $onAck);
    }

    /**
     * 主动推送语音到群聊
     */
    public function pushVoiceToGroup(string $chatId, string $filePath, ?callable $onAck = null): void
    {
        $this->pushMedia($chatId, $filePath, 'voice', 2, $onAck);
    }

    /**
     * 主动推送视频给用户（单聊）
     *
     * 支持格式：mp4；大小 ≤ 10MB。
     *
     * @param string        $userId      用户 userid
     * @param string        $filePath    本地视频文件路径
     * @param string|null   $title       视频标题（不超过 64 字节）
     * @param string|null   $description 视频描述（不超过 512 字节）
     * @param callable|null $onAck       ack 回调：fn(int $errcode) => void
     */
    public function pushVideoToUser(
        string $userId,
        string $filePath,
        ?string $title = null,
        ?string $description = null,
        ?callable $onAck = null,
    ): void {
        $this->pushMedia($userId, $filePath, 'video', 1, $onAck, $title, $description);
    }

    /**
     * 主动推送视频到群聊
     */
    public function pushVideoToGroup(
        string $chatId,
        string $filePath,
        ?string $title = null,
        ?string $description = null,
        ?callable $onAck = null,
    ): void {
        $this->pushMedia($chatId, $filePath, 'video', 2, $onAck, $title, $description);
    }

    /**
     * 内部媒体推送方法：上传文件 → 获取 media_id → 发送消息
     */
    private function pushMedia(
        string $chatId,
        string $filePath,
        string $type,
        int $chatType,
        ?callable $onAck = null,
        ?string $title = null,
        ?string $description = null,
    ): void {
        if (!$this->client?->isConnected()) {
            $this->logger->error("Cannot push {$type}: not connected");
            return;
        }

        $uploader = new MediaUploader();
        $uploader->upload($this->client, $type, $filePath, $this->logger, function (?string $mediaId, ?string $error) use (
            $chatId, $type, $chatType, $onAck, $title, $description
        ) {
            if ($mediaId === null) {
                $this->logger->error("Media upload failed: {$error}");
                return;
            }

            // 根据类型构建发送帧
            $frame = match ($type) {
                'image' => FrameBuilder::sendImage($chatId, $mediaId, $chatType),
                'file' => FrameBuilder::sendFile($chatId, $mediaId, $chatType),
                'voice' => FrameBuilder::sendVoice($chatId, $mediaId, $chatType),
                'video' => FrameBuilder::sendVideo($chatId, $mediaId, $chatType, $title, $description),
                default => throw new \InvalidArgumentException("Unsupported media type: {$type}"),
            };

            $decoded = json_decode($frame, true);
            $reqId = $decoded['headers']['req_id'] ?? '';

            $this->client->sendQueued($frame, $reqId, $onAck);
            $this->logger->info("Queued {$type} push to {$chatId} (media_id={$mediaId})");
        });
    }

    /**
     * 获取机器人 ID
     */
    public function getBotId(): string
    {
        return $this->botId;
    }

    /**
     * 获取内部 WsClient 实例（高级用途）
     */
    public function getClient(): ?WsClient
    {
        return $this->client;
    }

    // ========== 启动 ==========

    /**
     * 启动机器人（阻塞运行）
     *
     * 内部创建 Workerman Worker，在 onWorkerStart 中建立 WebSocket 连接。
     * 调用此方法后进程将阻塞在事件循环中。
     *
     * 如果需要在同一进程中运行多个机器人，请使用 BotManager。
     */
    public function start(): void
    {
        // 创建一个不监听端口的 Worker（纯客户端模式）
        $worker = new Worker();
        $worker->name = 'WeComAiBot';
        $worker->count = 1;

        $worker->onWorkerStart = function () {
            $this->logger->info("Worker started, connecting to {$this->wsUrl}...");
            $this->createAndConnect();
        };

        $worker->onWorkerStop = function () {
            $this->logger->info('Worker stopping...');
            $this->client?->disconnect();
        };

        // 运行 Worker（阻塞）
        Worker::runAll();
    }

    /**
     * 仅建立连接（不创建 Worker）
     *
     * 供 BotManager 在统一的 Worker 中调用，多个 bot 共享同一事件循环。
     * 不要直接调用此方法，请使用 start() 或 BotManager。
     *
     * @internal
     */
    public function connectOnly(): void
    {
        $this->logger->info("Connecting to {$this->wsUrl}...");
        $this->createAndConnect();
    }

    /**
     * 断开连接
     *
     * @internal
     */
    public function disconnectOnly(): void
    {
        $this->logger->info('Disconnecting...');
        $this->client?->disconnect();
    }

    // ========== 内部实现 ==========

    /**
     * 创建 WsClient 并建立连接
     */
    private function createAndConnect(): void
    {
        $this->client = new WsClient(
            botId: $this->botId,
            secret: $this->secret,
            logger: $this->logger,
            wsUrl: $this->wsUrl,
            heartbeatInterval: $this->heartbeatInterval,
            maxReconnectAttempts: $this->maxReconnectAttempts,
            ackTimeout: $this->ackTimeout,
        );

        // 注册消息回调
        $this->client->onMessage(function (array $frame) {
            $this->dispatchMessage($frame);
        });

        // 注册事件回调
        $this->client->onEvent(function (array $frame) {
            $this->dispatchEvent($frame);
        });

        // 注册认证成功回调
        $this->client->onAuthenticated(function () {
            if ($this->onAuthenticatedCallback) {
                ($this->onAuthenticatedCallback)();
            }
        });

        // 注册错误回调
        $this->client->onError(function (\Throwable $e) {
            if ($this->onErrorCallback) {
                ($this->onErrorCallback)($e);
            }
        });

        $this->client->connect();
    }

    /**
     * 分发消息到对应的 handler
     */
    private function dispatchMessage(array $frame): void
    {
        $message = MessageParser::parse($frame, $this->botId);

        if ($message === null) {
            $this->logger->warning('Failed to parse message, skipping');
            return;
        }

        // 消息去重（网络抖动可能收到重复消息）
        if ($message->id !== '' && isset($this->processedMsgIds[$message->id])) {
            $this->logger->debug("Duplicate message skipped: msgid={$message->id}");
            return;
        }
        if ($message->id !== '') {
            $this->processedMsgIds[$message->id] = true;
            // 超过容量上限时清理最早的一半
            if (count($this->processedMsgIds) > self::DEDUP_MAX_SIZE) {
                $this->processedMsgIds = array_slice($this->processedMsgIds, (int) (self::DEDUP_MAX_SIZE / 2), null, true);
            }
        }

        // 跳过空消息（没有文本、图片、文件）
        if (!$message->hasText() && !$message->hasImages() && !$message->hasFiles()) {
            $this->logger->debug('Skipping empty message');
            return;
        }

        $reply = new Reply($this->client, $message->reqId);

        // 触发类型特定的 handler
        $type = $message->type;
        if (isset($this->messageHandlers[$type])) {
            foreach ($this->messageHandlers[$type] as $handler) {
                try {
                    $handler($message, $reply);
                } catch (\Throwable $e) {
                    $this->logger->error("Message handler error [{$type}]: {$e->getMessage()}");
                }
            }
        }

        // 触发通配符 handler
        if (isset($this->messageHandlers['*'])) {
            foreach ($this->messageHandlers['*'] as $handler) {
                try {
                    $handler($message, $reply);
                } catch (\Throwable $e) {
                    $this->logger->error("Message handler error [*]: {$e->getMessage()}");
                }
            }
        }
    }

    /**
     * 分发事件到对应的 handler
     */
    private function dispatchEvent(array $frame): void
    {
        $body = $frame['body'] ?? [];
        $headers = $frame['headers'] ?? [];
        $reqId = $headers['req_id'] ?? '';

        $eventType = $body['event']['eventtype'] ?? '';
        $chatType = ($body['chattype'] ?? 'single') === 'group' ? 'group' : 'single';
        $chatId = $body['chatid'] ?? null;
        $senderId = $body['from']['userid'] ?? '';

        $createTime = isset($body['create_time']) ? (int) $body['create_time'] : null;

        $event = new Event(
            id: $body['msgid'] ?? '',
            reqId: $reqId,
            eventType: $eventType,
            chatType: $chatType,
            chatId: $chatId,
            senderId: $senderId,
            createTime: $createTime,
            eventData: $body['event'] ?? [],
            raw: $frame,
            botId: $this->botId,
        );

        $reply = new Reply($this->client, $reqId);

        // 触发类型特定的 handler
        if ($eventType !== '' && isset($this->eventHandlers[$eventType])) {
            foreach ($this->eventHandlers[$eventType] as $handler) {
                try {
                    $handler($event, $reply);
                } catch (\Throwable $e) {
                    $this->logger->error("Event handler error [{$eventType}]: {$e->getMessage()}");
                }
            }
        }

        // 触发通配符 handler
        if (isset($this->eventHandlers['*'])) {
            foreach ($this->eventHandlers['*'] as $handler) {
                try {
                    $handler($event, $reply);
                } catch (\Throwable $e) {
                    $this->logger->error("Event handler error [*]: {$e->getMessage()}");
                }
            }
        }
    }
}
