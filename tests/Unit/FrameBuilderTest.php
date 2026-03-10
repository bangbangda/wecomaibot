<?php

declare(strict_types=1);

namespace WeComAiBot\Tests\Unit;

use WeComAiBot\Protocol\Command;
use WeComAiBot\Protocol\FrameBuilder;
use WeComAiBot\Tests\TestCase;

class FrameBuilderTest extends TestCase
{
    public function test_generate_req_id_format(): void
    {
        $reqId = FrameBuilder::generateReqId('test');

        $this->assertStringStartsWith('test_', $reqId);
        // 格式：test_{timestamp}_{random_8hex}
        $this->assertMatchesRegularExpression('/^test_\d+_[0-9a-f]{8}$/', $reqId);
    }

    public function test_generate_req_id_uniqueness(): void
    {
        $ids = [];
        for ($i = 0; $i < 100; $i++) {
            $ids[] = FrameBuilder::generateReqId('test');
        }

        $this->assertCount(100, array_unique($ids), 'generateReqId should produce unique IDs');
    }

    public function test_auth_frame(): void
    {
        $frame = json_decode(FrameBuilder::auth('bot123', 'secret456'), true);

        $this->assertSame(Command::SUBSCRIBE, $frame['cmd']);
        $this->assertArrayHasKey('req_id', $frame['headers']);
        $this->assertStringStartsWith(Command::SUBSCRIBE . '_', $frame['headers']['req_id']);
        $this->assertSame('bot123', $frame['body']['bot_id']);
        $this->assertSame('secret456', $frame['body']['secret']);
    }

    public function test_ping_frame(): void
    {
        $frame = json_decode(FrameBuilder::ping(), true);

        $this->assertSame(Command::PING, $frame['cmd']);
        $this->assertArrayHasKey('req_id', $frame['headers']);
        $this->assertStringStartsWith(Command::PING . '_', $frame['headers']['req_id']);
        $this->assertArrayNotHasKey('body', $frame);
    }

    public function test_reply_stream_frame(): void
    {
        $frame = json_decode(
            FrameBuilder::replyStream('req_123', 'stream_456', '你好世界', false),
            true,
        );

        $this->assertSame(Command::RESPONSE, $frame['cmd']);
        $this->assertSame('req_123', $frame['headers']['req_id']);
        $this->assertSame('stream', $frame['body']['msgtype']);
        $this->assertSame('stream_456', $frame['body']['stream']['id']);
        $this->assertSame('你好世界', $frame['body']['stream']['content']);
        $this->assertFalse($frame['body']['stream']['finish']);
    }

    public function test_reply_stream_frame_finish(): void
    {
        $frame = json_decode(
            FrameBuilder::replyStream('req_123', 'stream_456', '最终回复', true),
            true,
        );

        $this->assertTrue($frame['body']['stream']['finish']);
        $this->assertSame('最终回复', $frame['body']['stream']['content']);
    }

    public function test_send_message_frame(): void
    {
        $frame = json_decode(
            FrameBuilder::sendMessage('user001', '**加粗文本**'),
            true,
        );

        $this->assertSame(Command::SEND_MSG, $frame['cmd']);
        $this->assertStringStartsWith(Command::SEND_MSG . '_', $frame['headers']['req_id']);
        $this->assertSame('user001', $frame['body']['chatid']);
        $this->assertSame('markdown', $frame['body']['msgtype']);
        $this->assertSame('**加粗文本**', $frame['body']['markdown']['content']);
    }

    public function test_reply_welcome_frame(): void
    {
        $frame = json_decode(
            FrameBuilder::replyWelcome('req_event_123', '欢迎！'),
            true,
        );

        $this->assertSame(Command::RESPONSE_WELCOME, $frame['cmd']);
        $this->assertSame('req_event_123', $frame['headers']['req_id']);
        $this->assertSame('text', $frame['body']['msgtype']);
        $this->assertSame('欢迎！', $frame['body']['text']['content']);
    }

    public function test_chinese_content_not_escaped(): void
    {
        $json = FrameBuilder::sendMessage('user1', '你好');

        // JSON 中中文不应被转义为 \uXXXX
        $this->assertStringContainsString('你好', $json);
        $this->assertStringNotContainsString('\u', $json);
    }
}
