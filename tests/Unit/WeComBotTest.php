<?php

declare(strict_types=1);

namespace WeComAiBot\Tests\Unit;

use WeComAiBot\WeComBot;
use WeComAiBot\Tests\TestCase;

class WeComBotTest extends TestCase
{
    public function test_constructor_requires_bot_id(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('bot_id');

        new WeComBot(['secret' => 'test']);
    }

    public function test_constructor_requires_secret(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('secret');

        new WeComBot(['bot_id' => 'test']);
    }

    public function test_constructor_accepts_valid_config(): void
    {
        $bot = new WeComBot([
            'bot_id' => 'test-bot',
            'secret' => 'test-secret',
        ]);

        $this->assertInstanceOf(WeComBot::class, $bot);
    }

    public function test_fluent_api(): void
    {
        $bot = new WeComBot([
            'bot_id' => 'test-bot',
            'secret' => 'test-secret',
        ]);

        // 所有 on* 方法应返回 $this 支持链式调用
        $result = $bot
            ->onMessage(fn() => null)
            ->onText(fn() => null)
            ->onImage(fn() => null)
            ->onVoice(fn() => null)
            ->onFile(fn() => null)
            ->onMixed(fn() => null)
            ->onEvent('enter_chat', fn() => null)
            ->onAuthenticated(fn() => null)
            ->onError(fn() => null);

        $this->assertSame($bot, $result);
    }

    public function test_get_client_returns_null_before_start(): void
    {
        $bot = new WeComBot([
            'bot_id' => 'test-bot',
            'secret' => 'test-secret',
        ]);

        $this->assertNull($bot->getClient());
    }
}
