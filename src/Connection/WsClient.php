<?php

declare(strict_types=1);

namespace WeComAiBot\Connection;

use Workerman\Connection\AsyncTcpConnection;
use Workerman\Timer;
use WeComAiBot\Protocol\Command;
use WeComAiBot\Protocol\FrameBuilder;
use WeComAiBot\Support\LoggerInterface;

/**
 * WebSocket 连接管理器
 *
 * 封装 Workerman AsyncTcpConnection，处理：
 * - WebSocket 连接建立
 * - 应用层认证（botId + secret）
 * - 应用层心跳（JSON ping 帧，30s 间隔）
 * - 断线自动重连（指数退避）
 * - 帧收发
 */
class WsClient
{
    /** 默认 WebSocket 地址 */
    private const DEFAULT_WS_URL = 'wss://openws.work.weixin.qq.com';

    /** 默认心跳间隔（秒） */
    private const DEFAULT_HEARTBEAT_INTERVAL = 30;

    /** 默认最大重连次数 */
    private const DEFAULT_MAX_RECONNECT_ATTEMPTS = 100;

    /** 重连最大延迟上限（秒） */
    private const RECONNECT_MAX_DELAY = 30;

    /** 连续丢失心跳 ack 的最大次数 */
    private const MAX_MISSED_PONG = 2;

    private ?AsyncTcpConnection $connection = null;
    private bool $authenticated = false;
    private bool $manualClose = false;
    private int $reconnectAttempts = 0;
    private int $missedPongCount = 0;

    /** 心跳定时器 ID */
    private ?int $heartbeatTimerId = null;

    /** 收到消息的回调 */
    private mixed $onMessageCallback = null;

    /** 收到事件的回调 */
    private mixed $onEventCallback = null;

    /** 认证成功回调 */
    private mixed $onAuthenticatedCallback = null;

    /** 连接断开回调 */
    private mixed $onDisconnectedCallback = null;

    /** 错误回调 */
    private mixed $onErrorCallback = null;

    public function __construct(
        private readonly string $botId,
        private readonly string $secret,
        private readonly LoggerInterface $logger,
        private readonly string $wsUrl = self::DEFAULT_WS_URL,
        private readonly int $heartbeatInterval = self::DEFAULT_HEARTBEAT_INTERVAL,
        private readonly int $maxReconnectAttempts = self::DEFAULT_MAX_RECONNECT_ATTEMPTS,
    ) {
    }

    // ========== 事件注册 ==========

    /**
     * 设置消息回调
     *
     * @param callable(array): void $callback 接收原始帧数据
     */
    public function onMessage(callable $callback): void
    {
        $this->onMessageCallback = $callback;
    }

    /**
     * 设置事件回调
     *
     * @param callable(array): void $callback 接收原始帧数据
     */
    public function onEvent(callable $callback): void
    {
        $this->onEventCallback = $callback;
    }

    /**
     * 设置认证成功回调
     */
    public function onAuthenticated(callable $callback): void
    {
        $this->onAuthenticatedCallback = $callback;
    }

    /**
     * 设置连接断开回调
     */
    public function onDisconnected(callable $callback): void
    {
        $this->onDisconnectedCallback = $callback;
    }

    /**
     * 设置错误回调
     */
    public function onError(callable $callback): void
    {
        $this->onErrorCallback = $callback;
    }

    // ========== 连接管理 ==========

    /**
     * 建立 WebSocket 连接
     *
     * 连接成功后自动发送认证帧
     */
    public function connect(): void
    {
        $this->manualClose = false;

        // 解析 WebSocket URL 为 AsyncTcpConnection 格式
        $url = $this->wsUrl;
        $useSsl = str_starts_with($url, 'wss://');

        // AsyncTcpConnection 使用 ws:// 协议，SSL 通过 transport 属性设置
        if ($useSsl) {
            $url = 'ws://' . substr($url, 6);
        }

        $this->logger->info("Connecting to {$this->wsUrl}...");

        try {
            $this->connection = new AsyncTcpConnection($url);

            if ($useSsl) {
                $this->connection->transport = 'ssl';
            }

            $this->setupEventHandlers();
            $this->connection->connect();
        } catch (\Throwable $e) {
            $this->logger->error("Connect failed: {$e->getMessage()}");
            $this->fireError($e);
            $this->scheduleReconnect();
        }
    }

    /**
     * 主动断开连接
     */
    public function disconnect(): void
    {
        $this->manualClose = true;
        $this->stopHeartbeat();
        $this->authenticated = false;

        if ($this->connection) {
            $this->connection->close();
            $this->connection = null;
        }

        $this->logger->info('Connection manually closed');
    }

    /**
     * 发送数据帧
     *
     * @param string $data JSON 字符串
     */
    public function send(string $data): void
    {
        if (!$this->connection) {
            $this->logger->error('Cannot send: connection not established');
            return;
        }

        $this->connection->send($data);
    }

    /**
     * 当前是否已连接且认证通过
     */
    public function isConnected(): bool
    {
        return $this->connection !== null && $this->authenticated;
    }

    // ========== 内部实现 ==========

    /**
     * 设置 WebSocket 事件处理
     */
    private function setupEventHandlers(): void
    {
        if (!$this->connection) {
            return;
        }

        // WebSocket 握手成功 → 发送认证帧
        $this->connection->onWebSocketConnect = function () {
            $this->logger->info('WebSocket connected, sending auth...');
            $this->reconnectAttempts = 0;
            $this->missedPongCount = 0;
            $this->send(FrameBuilder::auth($this->botId, $this->secret));
        };

        // 收到消息 → 解析帧并分发
        $this->connection->onMessage = function (AsyncTcpConnection $conn, string $data) {
            try {
                $frame = json_decode($data, true, 512, JSON_THROW_ON_ERROR);
                $this->handleFrame($frame);
            } catch (\JsonException $e) {
                $this->logger->error("Failed to parse frame: {$e->getMessage()}");
            }
        };

        // 连接关闭 → 重连
        $this->connection->onClose = function () {
            $this->logger->warning('Connection closed');
            $this->authenticated = false;
            $this->stopHeartbeat();
            $this->connection = null;

            if ($this->onDisconnectedCallback) {
                ($this->onDisconnectedCallback)();
            }

            if (!$this->manualClose) {
                $this->scheduleReconnect();
            }
        };

        // 连接错误
        $this->connection->onError = function (AsyncTcpConnection $conn, int $code, string $msg) {
            $this->logger->error("Connection error [{$code}]: {$msg}");
            $this->fireError(new \RuntimeException("Connection error [{$code}]: {$msg}"));
        };
    }

    /**
     * 处理收到的帧
     */
    private function handleFrame(array $frame): void
    {
        $cmd = $frame['cmd'] ?? '';
        $reqId = $frame['headers']['req_id'] ?? '';

        // 消息推送
        if ($cmd === Command::CALLBACK) {
            $msgid = $frame['body']['msgid'] ?? 'unknown';
            $this->logger->debug("Received message: msgid={$msgid}");
            if ($this->onMessageCallback) {
                ($this->onMessageCallback)($frame);
            }
            return;
        }

        // 事件推送
        if ($cmd === Command::EVENT_CALLBACK) {
            $eventtype = $frame['body']['event']['eventtype'] ?? 'unknown';
            $this->logger->debug("Received event: {$eventtype}");
            if ($this->onEventCallback) {
                ($this->onEventCallback)($frame);
            }
            return;
        }

        // 认证响应（req_id 以 aibot_subscribe 开头）
        if (str_starts_with($reqId, Command::SUBSCRIBE)) {
            $errcode = $frame['errcode'] ?? -1;
            if ($errcode === 0) {
                $this->logger->info('Authenticated successfully');
                $this->authenticated = true;
                $this->startHeartbeat();
                if ($this->onAuthenticatedCallback) {
                    ($this->onAuthenticatedCallback)();
                }
            } else {
                $errmsg = $frame['errmsg'] ?? 'unknown';
                $this->logger->error("Authentication failed: [{$errcode}] {$errmsg}");
                $this->fireError(new \RuntimeException("Authentication failed: [{$errcode}] {$errmsg}"));
                // 认证失败不重连（密码错重连没意义）
                $this->manualClose = true;
                $this->disconnect();
            }
            return;
        }

        // 心跳响应（req_id 以 ping 开头）
        if (str_starts_with($reqId, Command::PING)) {
            $errcode = $frame['errcode'] ?? -1;
            if ($errcode === 0) {
                $this->missedPongCount = 0;
                $this->logger->debug('Heartbeat ack received');
            } else {
                $this->logger->warning("Heartbeat ack error: errcode={$errcode}");
            }
            return;
        }

        // 回复消息回执（V1 仅记录日志）
        $ackErrcode = $frame['errcode'] ?? 'N/A';
        $this->logger->debug("Ack frame: reqId={$reqId}, errcode={$ackErrcode}");
    }

    /**
     * 启动应用层心跳定时器
     */
    private function startHeartbeat(): void
    {
        $this->stopHeartbeat();

        $this->heartbeatTimerId = Timer::add($this->heartbeatInterval, function () {
            // 检查丢失心跳次数
            if ($this->missedPongCount >= self::MAX_MISSED_PONG) {
                $this->logger->warning("No heartbeat ack for {$this->missedPongCount} pings, connection dead");
                $this->stopHeartbeat();
                // 强制关闭连接，触发 onClose → 重连
                if ($this->connection) {
                    $this->connection->close();
                }
                return;
            }

            $this->missedPongCount++;
            $this->send(FrameBuilder::ping());
            $this->logger->debug('Heartbeat sent');
        });

        $this->logger->debug("Heartbeat started, interval={$this->heartbeatInterval}s");
    }

    /**
     * 停止心跳定时器
     */
    private function stopHeartbeat(): void
    {
        if ($this->heartbeatTimerId !== null) {
            Timer::del($this->heartbeatTimerId);
            $this->heartbeatTimerId = null;
        }
    }

    /**
     * 安排重连（指数退避）
     */
    private function scheduleReconnect(): void
    {
        if ($this->maxReconnectAttempts !== -1 && $this->reconnectAttempts >= $this->maxReconnectAttempts) {
            $this->logger->error("Max reconnect attempts reached ({$this->maxReconnectAttempts})");
            $this->fireError(new \RuntimeException('Max reconnect attempts exceeded'));
            return;
        }

        $this->reconnectAttempts++;

        // 指数退避：1s, 2s, 4s, 8s, ... 上限 30s
        $delay = min(pow(2, $this->reconnectAttempts - 1), self::RECONNECT_MAX_DELAY);

        $this->logger->info("Reconnecting in {$delay}s (attempt {$this->reconnectAttempts}/{$this->maxReconnectAttempts})...");

        Timer::add($delay, function () {
            if ($this->manualClose) {
                return;
            }
            $this->connect();
        }, [], false); // false = 一次性定时器
    }

    /**
     * 触发错误回调
     */
    private function fireError(\Throwable $e): void
    {
        if ($this->onErrorCallback) {
            ($this->onErrorCallback)($e);
        }
    }
}
