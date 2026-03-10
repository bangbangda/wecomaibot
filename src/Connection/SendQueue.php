<?php

declare(strict_types=1);

namespace WeComAiBot\Connection;

use Workerman\Timer;
use WeComAiBot\Support\LoggerInterface;

/**
 * 发送队列 — 串行发送帧并等待 ack
 *
 * 企微 WebSocket 协议要求：上一帧收到 ack 后才能发下一帧，
 * 否则可能出现消息丢失或乱序。
 *
 * 工作流程：
 * 1. enqueue() 将帧加入队列
 * 2. 自动取出队首帧发送
 * 3. 等待服务端 ack（通过 handleAck() 通知）
 * 4. 收到 ack 后发送下一帧
 * 5. 超时未收到 ack → 记录错误 → 发送下一帧
 */
class SendQueue
{
    /** 默认 ack 超时（秒） */
    private const DEFAULT_ACK_TIMEOUT = 10;

    /** 队列：每项 = ['data' => string, 'reqId' => string, 'onAck' => ?callable] */
    private array $queue = [];

    /** 当前正在等待 ack 的 reqId */
    private ?string $pendingReqId = null;

    /** 当前超时定时器 ID */
    private ?int $timeoutTimerId = null;

    /** 当前等待项的 onAck 回调 */
    private mixed $pendingOnAck = null;

    /** 实际发送函数 */
    private mixed $sendFn;

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly int $ackTimeout = self::DEFAULT_ACK_TIMEOUT,
    ) {
    }

    /**
     * 设置底层发送函数
     *
     * @param callable(string): void $fn 接收 JSON 字符串并发送
     */
    public function setSendFn(callable $fn): void
    {
        $this->sendFn = $fn;
    }

    /**
     * 将帧加入发送队列
     *
     * @param string        $data  JSON 帧字符串
     * @param string        $reqId 帧的 req_id，用于匹配 ack
     * @param callable|null $onAck ack 回调：fn(int $errcode) => void
     */
    public function enqueue(string $data, string $reqId, ?callable $onAck = null): void
    {
        $this->queue[] = [
            'data' => $data,
            'reqId' => $reqId,
            'onAck' => $onAck,
        ];

        // 如果当前没有等待中的帧，立即发送
        if ($this->pendingReqId === null) {
            $this->sendNext();
        }
    }

    /**
     * 处理收到的 ack 帧
     *
     * @param string $reqId   ack 对应的 req_id
     * @param int    $errcode 错误码（0 表示成功）
     */
    public function handleAck(string $reqId, int $errcode): void
    {
        // 不是当前等待的 ack，忽略
        if ($this->pendingReqId === null || $this->pendingReqId !== $reqId) {
            $this->logger->debug("Unexpected ack for reqId={$reqId}, ignoring");
            return;
        }

        $this->clearTimeout();

        // 触发回调
        if ($this->pendingOnAck) {
            try {
                ($this->pendingOnAck)($errcode);
            } catch (\Throwable $e) {
                $this->logger->error("Ack callback error: {$e->getMessage()}");
            }
        }

        if ($errcode !== 0) {
            $this->logger->warning("Ack error for reqId={$reqId}: errcode={$errcode}");
        } else {
            $this->logger->debug("Ack OK for reqId={$reqId}");
        }

        $this->pendingReqId = null;
        $this->pendingOnAck = null;

        // 发送下一帧
        $this->sendNext();
    }

    /**
     * 清空队列（断线时调用）
     */
    public function clear(): void
    {
        $this->queue = [];
        $this->clearTimeout();
        $this->pendingReqId = null;
        $this->pendingOnAck = null;
    }

    /**
     * 获取队列长度（含当前等待中的帧）
     */
    public function size(): int
    {
        return count($this->queue) + ($this->pendingReqId !== null ? 1 : 0);
    }

    /**
     * 发送队列中的下一帧
     */
    private function sendNext(): void
    {
        if (empty($this->queue)) {
            return;
        }

        if (!isset($this->sendFn)) {
            $this->logger->error('SendQueue: sendFn not set');
            return;
        }

        $item = array_shift($this->queue);
        $this->pendingReqId = $item['reqId'];
        $this->pendingOnAck = $item['onAck'];

        // 发送
        ($this->sendFn)($item['data']);

        // 启动超时定时器（Workerman 事件循环中才可用）
        $this->startTimeout();
    }

    /**
     * 启动 ack 超时定时器
     */
    private function startTimeout(): void
    {
        try {
            $this->timeoutTimerId = Timer::add($this->ackTimeout, function () {
                $reqId = $this->pendingReqId;
                $this->logger->warning("Ack timeout for reqId={$reqId} after {$this->ackTimeout}s");

                // 触发回调（-1 表示超时）
                if ($this->pendingOnAck) {
                    try {
                        ($this->pendingOnAck)(-1);
                    } catch (\Throwable $e) {
                        $this->logger->error("Ack timeout callback error: {$e->getMessage()}");
                    }
                }

                $this->pendingReqId = null;
                $this->pendingOnAck = null;
                $this->timeoutTimerId = null;

                // 继续发送下一帧
                $this->sendNext();
            }, [], false); // 一次性定时器
        } catch (\RuntimeException) {
            // Workerman 事件循环未启动（如单元测试环境），超时保护不可用
        }
    }

    /**
     * 清除超时定时器
     */
    private function clearTimeout(): void
    {
        if ($this->timeoutTimerId !== null) {
            try {
                Timer::del($this->timeoutTimerId);
            } catch (\RuntimeException) {
                // Workerman 事件循环未启动
            }
            $this->timeoutTimerId = null;
        }
    }
}
