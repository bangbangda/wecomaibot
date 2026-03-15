<?php

declare(strict_types=1);

namespace WeComAiBot\Tests\Unit;

use WeComAiBot\Message\MessageParser;
use WeComAiBot\Tests\TestCase;

class MessageParserTest extends TestCase
{
    public function test_parse_text_message(): void
    {
        $frame = $this->buildFrame([
            'msgid' => 'msg001',
            'msgtype' => 'text',
            'chattype' => 'single',
            'from' => ['userid' => 'user001'],
            'text' => ['content' => '你好'],
        ]);

        $message = MessageParser::parse($frame);

        $this->assertNotNull($message);
        $this->assertSame('msg001', $message->id);
        $this->assertSame('req_test_001', $message->reqId);
        $this->assertSame('text', $message->type);
        $this->assertSame('single', $message->chatType);
        $this->assertSame('user001', $message->chatId);
        $this->assertSame('user001', $message->senderId);
        $this->assertSame('你好', $message->text);
        $this->assertTrue($message->isDirect());
        $this->assertFalse($message->isGroup());
    }

    public function test_parse_group_message(): void
    {
        $frame = $this->buildFrame([
            'msgid' => 'msg002',
            'msgtype' => 'text',
            'chattype' => 'group',
            'chatid' => 'group001',
            'from' => ['userid' => 'user001'],
            'text' => ['content' => '大家好'],
        ]);

        $message = MessageParser::parse($frame);

        $this->assertSame('group', $message->chatType);
        $this->assertSame('group001', $message->chatId);
        $this->assertTrue($message->isGroup());
        $this->assertSame('大家好', $message->text);
    }

    public function test_parse_group_message_removes_mention(): void
    {
        $frame = $this->buildFrame([
            'msgid' => 'msg003',
            'msgtype' => 'text',
            'chattype' => 'group',
            'chatid' => 'group001',
            'from' => ['userid' => 'user001'],
            'text' => ['content' => '@Bot 帮我查一下'],
        ]);

        $message = MessageParser::parse($frame);

        $this->assertSame('帮我查一下', $message->text);
    }

    public function test_parse_voice_message(): void
    {
        $frame = $this->buildFrame([
            'msgid' => 'msg004',
            'msgtype' => 'voice',
            'chattype' => 'single',
            'from' => ['userid' => 'user001'],
            'voice' => ['content' => '明天下午三点开会'],
        ]);

        $message = MessageParser::parse($frame);

        $this->assertSame('voice', $message->type);
        $this->assertSame('明天下午三点开会', $message->text);
    }

    public function test_parse_image_message(): void
    {
        $frame = $this->buildFrame([
            'msgid' => 'msg005',
            'msgtype' => 'image',
            'chattype' => 'single',
            'from' => ['userid' => 'user001'],
            'image' => [
                'url' => 'https://example.com/img.jpg',
                'aeskey' => 'abc123key',
            ],
        ]);

        $message = MessageParser::parse($frame);

        $this->assertSame('image', $message->type);
        $this->assertTrue($message->hasImages());
        $this->assertCount(1, $message->imageUrls);
        $this->assertSame('https://example.com/img.jpg', $message->imageUrls[0]);
        $this->assertSame('abc123key', $message->imageAesKeys['https://example.com/img.jpg']);
    }

    public function test_parse_file_message(): void
    {
        $frame = $this->buildFrame([
            'msgid' => 'msg006',
            'msgtype' => 'file',
            'chattype' => 'single',
            'from' => ['userid' => 'user001'],
            'file' => [
                'url' => 'https://example.com/doc.pdf',
                'aeskey' => 'filekey123',
            ],
        ]);

        $message = MessageParser::parse($frame);

        $this->assertSame('file', $message->type);
        $this->assertTrue($message->hasFiles());
        $this->assertCount(1, $message->fileUrls);
        $this->assertSame('https://example.com/doc.pdf', $message->fileUrls[0]);
        $this->assertSame('filekey123', $message->fileAesKeys['https://example.com/doc.pdf']);
    }

    public function test_parse_mixed_message(): void
    {
        $frame = $this->buildFrame([
            'msgid' => 'msg007',
            'msgtype' => 'mixed',
            'chattype' => 'single',
            'from' => ['userid' => 'user001'],
            'mixed' => [
                'msg_item' => [
                    ['msgtype' => 'text', 'text' => ['content' => '看这张图']],
                    ['msgtype' => 'image', 'image' => ['url' => 'https://example.com/pic.png']],
                ],
            ],
        ]);

        $message = MessageParser::parse($frame);

        $this->assertSame('mixed', $message->type);
        $this->assertSame('看这张图', $message->text);
        $this->assertTrue($message->hasImages());
        $this->assertSame('https://example.com/pic.png', $message->imageUrls[0]);
    }

    public function test_parse_message_with_quote(): void
    {
        $frame = $this->buildFrame([
            'msgid' => 'msg008',
            'msgtype' => 'text',
            'chattype' => 'single',
            'from' => ['userid' => 'user001'],
            'text' => ['content' => '同意'],
            'quote' => [
                'msgtype' => 'text',
                'text' => ['content' => '明天开会吗？'],
            ],
        ]);

        $message = MessageParser::parse($frame);

        $this->assertTrue($message->hasQuote());
        $this->assertSame('明天开会吗？', $message->quoteContent);
        $this->assertSame('同意', $message->text);
    }

    public function test_parse_empty_text_with_quote_uses_quote_content(): void
    {
        // 用户只 @机器人 但没有文本，有引用消息
        $frame = $this->buildFrame([
            'msgid' => 'msg009',
            'msgtype' => 'text',
            'chattype' => 'group',
            'chatid' => 'group001',
            'from' => ['userid' => 'user001'],
            'text' => ['content' => '@Bot'],
            'quote' => [
                'msgtype' => 'text',
                'text' => ['content' => '这个怎么办？'],
            ],
        ]);

        $message = MessageParser::parse($frame);

        // @Bot 被去除后文本为空，应使用引用内容
        $this->assertSame('这个怎么办？', $message->text);
    }

    public function test_parse_quote_with_image(): void
    {
        $frame = $this->buildFrame([
            'msgid' => 'msg010',
            'msgtype' => 'text',
            'chattype' => 'single',
            'from' => ['userid' => 'user001'],
            'text' => ['content' => '这张图是什么？'],
            'quote' => [
                'msgtype' => 'image',
                'image' => [
                    'url' => 'https://example.com/quoted.jpg',
                    'aeskey' => 'quotedkey',
                ],
            ],
        ]);

        $message = MessageParser::parse($frame);

        $this->assertTrue($message->hasImages());
        $this->assertSame('https://example.com/quoted.jpg', $message->imageUrls[0]);
        $this->assertSame('quotedkey', $message->imageAesKeys['https://example.com/quoted.jpg']);
    }

    public function test_parse_returns_null_for_empty_body(): void
    {
        $this->assertNull(MessageParser::parse([]));
        $this->assertNull(MessageParser::parse(['body' => []]));
        $this->assertNull(MessageParser::parse(['body' => ['msgtype' => '']]));
    }

    public function test_parse_returns_null_for_missing_msgtype(): void
    {
        $frame = [
            'headers' => ['req_id' => 'req_test'],
            'body' => ['msgid' => 'msg_test'],
        ];

        $this->assertNull(MessageParser::parse($frame));
    }

    public function test_parse_injects_bot_id(): void
    {
        $frame = $this->buildFrame([
            'msgid' => 'msg020',
            'msgtype' => 'text',
            'chattype' => 'single',
            'from' => ['userid' => 'user001'],
            'text' => ['content' => '你好'],
        ]);

        $message = MessageParser::parse($frame, 'my-bot-id');

        $this->assertSame('my-bot-id', $message->botId);
    }

    public function test_parse_bot_id_defaults_to_empty(): void
    {
        $frame = $this->buildFrame([
            'msgid' => 'msg021',
            'msgtype' => 'text',
            'chattype' => 'single',
            'from' => ['userid' => 'user001'],
            'text' => ['content' => '你好'],
        ]);

        $message = MessageParser::parse($frame);

        $this->assertSame('', $message->botId);
    }

    public function test_parse_different_bot_ids_produce_different_messages(): void
    {
        $frame = $this->buildFrame([
            'msgid' => 'msg022',
            'msgtype' => 'text',
            'chattype' => 'single',
            'from' => ['userid' => 'user001'],
            'text' => ['content' => '同一条消息'],
        ]);

        $msgA = MessageParser::parse($frame, 'bot-a');
        $msgB = MessageParser::parse($frame, 'bot-b');

        $this->assertSame('bot-a', $msgA->botId);
        $this->assertSame('bot-b', $msgB->botId);
        $this->assertSame($msgA->text, $msgB->text);
    }

    public function test_message_helper_methods(): void
    {
        $frame = $this->buildFrame([
            'msgid' => 'msg011',
            'msgtype' => 'text',
            'chattype' => 'single',
            'from' => ['userid' => 'user001'],
            'text' => ['content' => 'hello'],
        ]);

        $message = MessageParser::parse($frame);

        $this->assertTrue($message->hasText());
        $this->assertFalse($message->hasImages());
        $this->assertFalse($message->hasFiles());
        $this->assertFalse($message->hasQuote());
        $this->assertTrue($message->isDirect());
        $this->assertFalse($message->isGroup());
    }

    /**
     * 构建测试用的帧数据
     */
    private function buildFrame(array $body, string $reqId = 'req_test_001'): array
    {
        return [
            'cmd' => 'aibot_msg_callback',
            'headers' => ['req_id' => $reqId],
            'body' => $body,
        ];
    }
}
