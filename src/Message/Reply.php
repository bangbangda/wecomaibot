<?php

declare(strict_types=1);

namespace WeComAiBot\Message;

use WeComAiBot\Connection\WsClient;
use WeComAiBot\Protocol\FrameBuilder;

/**
 * 回复操作对象
 *
 * 绑定特定消息的 req_id，提供便捷的回复方法。
 * 每条收到的消息对应一个 Reply 实例。
 */
class Reply
{
    /** 流式回复的 stream_id，首次调用 stream() 时自动生成 */
    private ?string $streamId = null;

    /**
     * @param WsClient $client WebSocket 客户端实例
     * @param string   $reqId  原始消息的 req_id
     */
    public function __construct(
        private readonly WsClient $client,
        private readonly string $reqId,
    ) {
    }

    /**
     * 发送完整文本回复（自动结束流式消息）
     *
     * @param string $content 回复内容（支持 Markdown）
     */
    public function text(string $content): void
    {
        $this->stream($content, finish: true);
    }

    /**
     * 发送流式回复
     *
     * 首次调用自动生成 streamId，后续调用共用同一个 streamId。
     * 最后一次调用需设置 finish: true 结束流式消息。
     *
     * @param string $content 回复内容（支持 Markdown，内容为累积全文而非增量）
     * @param bool   $finish  是否结束流式消息
     */
    public function stream(string $content, bool $finish = false): void
    {
        if ($this->streamId === null) {
            $this->streamId = FrameBuilder::generateReqId('stream');
        }

        $frame = FrameBuilder::replyStream(
            reqId: $this->reqId,
            streamId: $this->streamId,
            content: $content,
            finish: $finish,
        );

        $this->client->send($frame);
    }

    /**
     * 获取当前绑定的 req_id
     */
    public function getReqId(): string
    {
        return $this->reqId;
    }

    /**
     * 获取当前的 stream_id（未开始流式回复时返回 null）
     */
    public function getStreamId(): ?string
    {
        return $this->streamId;
    }
}
