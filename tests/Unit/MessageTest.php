<?php

declare(strict_types=1);

namespace WeComAiBot\Tests\Unit;

use WeComAiBot\Message\Message;
use WeComAiBot\Tests\TestCase;

class MessageTest extends TestCase
{
    public function test_readonly_properties(): void
    {
        $message = new Message(
            id: 'msg001',
            reqId: 'req001',
            type: 'text',
            chatType: 'single',
            chatId: 'user001',
            senderId: 'user001',
            text: '你好',
        );

        $this->assertSame('msg001', $message->id);
        $this->assertSame('req001', $message->reqId);
        $this->assertSame('text', $message->type);
        $this->assertSame('single', $message->chatType);
        $this->assertSame('user001', $message->chatId);
        $this->assertSame('user001', $message->senderId);
        $this->assertSame('你好', $message->text);
    }

    public function test_is_group(): void
    {
        $groupMsg = new Message(id: '1', reqId: 'r', type: 'text', chatType: 'group', chatId: 'g1', senderId: 'u1');
        $directMsg = new Message(id: '2', reqId: 'r', type: 'text', chatType: 'single', chatId: 'u1', senderId: 'u1');

        $this->assertTrue($groupMsg->isGroup());
        $this->assertFalse($groupMsg->isDirect());
        $this->assertFalse($directMsg->isGroup());
        $this->assertTrue($directMsg->isDirect());
    }

    public function test_has_methods(): void
    {
        $withAll = new Message(
            id: '1', reqId: 'r', type: 'text', chatType: 'single', chatId: 'u', senderId: 'u',
            text: 'hello',
            imageUrls: ['https://img.com/1.jpg'],
            fileUrls: ['https://file.com/1.pdf'],
            quoteContent: 'quoted text',
        );

        $this->assertTrue($withAll->hasText());
        $this->assertTrue($withAll->hasImages());
        $this->assertTrue($withAll->hasFiles());
        $this->assertTrue($withAll->hasQuote());

        $empty = new Message(id: '2', reqId: 'r', type: 'text', chatType: 'single', chatId: 'u', senderId: 'u');

        $this->assertFalse($empty->hasText());
        $this->assertFalse($empty->hasImages());
        $this->assertFalse($empty->hasFiles());
        $this->assertFalse($empty->hasQuote());
    }

    public function test_default_values(): void
    {
        $message = new Message(id: '1', reqId: 'r', type: 'text', chatType: 'single', chatId: 'u', senderId: 'u');

        $this->assertSame('', $message->text);
        $this->assertSame([], $message->imageUrls);
        $this->assertSame([], $message->fileUrls);
        $this->assertNull($message->quoteContent);
        $this->assertSame([], $message->imageAesKeys);
        $this->assertSame([], $message->fileAesKeys);
        $this->assertSame([], $message->raw);
    }
}
