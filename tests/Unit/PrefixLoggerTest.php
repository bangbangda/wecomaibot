<?php

declare(strict_types=1);

namespace WeComAiBot\Tests\Unit;

use WeComAiBot\Support\PrefixLogger;
use WeComAiBot\Tests\TestCase;

class PrefixLoggerTest extends TestCase
{
    public function test_prefix_added_to_all_levels(): void
    {
        $messages = [];

        $inner = new class($messages) implements \WeComAiBot\Support\LoggerInterface {
            private array $messages;
            public function __construct(array &$messages) { $this->messages = &$messages; }
            public function debug(string $message): void { $this->messages[] = ['debug', $message]; }
            public function info(string $message): void { $this->messages[] = ['info', $message]; }
            public function warning(string $message): void { $this->messages[] = ['warning', $message]; }
            public function error(string $message): void { $this->messages[] = ['error', $message]; }
        };

        $logger = new PrefixLogger($inner, '[sales] ');

        $logger->debug('heartbeat');
        $logger->info('connected');
        $logger->warning('reconnecting');
        $logger->error('auth failed');

        $this->assertCount(4, $messages);
        $this->assertSame(['debug', '[sales] heartbeat'], $messages[0]);
        $this->assertSame(['info', '[sales] connected'], $messages[1]);
        $this->assertSame(['warning', '[sales] reconnecting'], $messages[2]);
        $this->assertSame(['error', '[sales] auth failed'], $messages[3]);
    }
}
