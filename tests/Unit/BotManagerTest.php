<?php

declare(strict_types=1);

namespace WeComAiBot\Tests\Unit;

use WeComAiBot\BotManager;
use WeComAiBot\WeComBot;
use WeComAiBot\Tests\TestCase;

class BotManagerTest extends TestCase
{
    public function test_constructor_with_configs(): void
    {
        $manager = new BotManager([
            ['bot_id' => 'bot-1', 'secret' => 'secret-1'],
            ['bot_id' => 'bot-2', 'secret' => 'secret-2'],
        ]);

        $this->assertCount(2, $manager->getAllBots());
        $this->assertInstanceOf(WeComBot::class, $manager->getBot('bot-1'));
        $this->assertInstanceOf(WeComBot::class, $manager->getBot('bot-2'));
    }

    public function test_constructor_empty_configs(): void
    {
        $manager = new BotManager();

        $this->assertEmpty($manager->getAllBots());
    }

    public function test_add_bot_returns_wecom_bot_instance(): void
    {
        $manager = new BotManager();

        $bot = $manager->addBot([
            'bot_id' => 'sales-bot-id',
            'secret' => 'sales-secret',
        ]);

        $this->assertInstanceOf(WeComBot::class, $bot);
    }

    public function test_get_bot_returns_registered_instance(): void
    {
        $manager = new BotManager();

        $bot = $manager->addBot([
            'bot_id' => 'sales-bot-id',
            'secret' => 'sales-secret',
        ]);

        $this->assertSame($bot, $manager->getBot('sales-bot-id'));
    }

    public function test_get_bot_returns_null_for_unknown(): void
    {
        $manager = new BotManager();

        $this->assertNull($manager->getBot('nonexistent'));
    }

    public function test_duplicate_bot_id_throws_exception(): void
    {
        $manager = new BotManager();

        $manager->addBot([
            'bot_id' => 'bot-1',
            'secret' => 'secret-1',
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Bot "bot-1" already registered');

        $manager->addBot([
            'bot_id' => 'bot-1',
            'secret' => 'secret-2',
        ]);
    }

    public function test_add_bot_without_bot_id_throws_exception(): void
    {
        $manager = new BotManager();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('bot_id');

        $manager->addBot(['secret' => 'secret-1']);
    }

    public function test_get_all_bots(): void
    {
        $manager = new BotManager();

        $sales = $manager->addBot([
            'bot_id' => 'sales-id',
            'secret' => 'sales-secret',
        ]);

        $finance = $manager->addBot([
            'bot_id' => 'finance-id',
            'secret' => 'finance-secret',
        ]);

        $all = $manager->getAllBots();

        $this->assertCount(2, $all);
        $this->assertSame($sales, $all['sales-id']);
        $this->assertSame($finance, $all['finance-id']);
    }

    public function test_remove_bot(): void
    {
        $manager = new BotManager();

        $manager->addBot([
            'bot_id' => 'sales-id',
            'secret' => 'sales-secret',
        ]);

        $manager->removeBot('sales-id');

        $this->assertNull($manager->getBot('sales-id'));
        $this->assertEmpty($manager->getAllBots());
    }

    public function test_remove_nonexistent_bot_is_safe(): void
    {
        $manager = new BotManager();

        // 不抛异常
        $manager->removeBot('nonexistent');

        $this->assertEmpty($manager->getAllBots());
    }

    public function test_bot_id_is_accessible(): void
    {
        $manager = new BotManager();

        $bot = $manager->addBot([
            'bot_id' => 'my-bot',
            'secret' => 'my-secret',
        ]);

        $this->assertSame('my-bot', $bot->getBotId());
    }

    public function test_multiple_bots_are_independent(): void
    {
        $manager = new BotManager([
            ['bot_id' => 'sales-id', 'secret' => 'sales-secret'],
            ['bot_id' => 'finance-id', 'secret' => 'finance-secret'],
        ]);

        $sales = $manager->getBot('sales-id');
        $finance = $manager->getBot('finance-id');

        // 各自注册不同的回调，互不干扰
        $salesCalled = false;
        $financeCalled = false;

        $sales->onMessage(function () use (&$salesCalled) {
            $salesCalled = true;
        });

        $finance->onMessage(function () use (&$financeCalled) {
            $financeCalled = true;
        });

        $this->assertNotSame($sales, $finance);
        $this->assertSame('sales-id', $sales->getBotId());
        $this->assertSame('finance-id', $finance->getBotId());
    }
}
