<?php

declare(strict_types=1);

namespace WeComAiBot\Support;

/**
 * 默认控制台日志实现
 *
 * 带时间戳和级别前缀输出到 STDOUT/STDERR
 */
class ConsoleLogger implements LoggerInterface
{
    public function __construct(
        private readonly string $prefix = 'WeComAiBot',
    ) {
    }

    public function debug(string $message): void
    {
        $this->log('DEBUG', $message);
    }

    public function info(string $message): void
    {
        $this->log('INFO', $message);
    }

    public function warning(string $message): void
    {
        $this->log('WARN', $message);
    }

    public function error(string $message): void
    {
        $this->log('ERROR', $message, STDERR);
    }

    /**
     * @param resource $stream
     */
    private function log(string $level, string $message, $stream = STDOUT): void
    {
        $time = date('Y-m-d H:i:s');
        fwrite($stream, "[{$time}] [{$this->prefix}] [{$level}] {$message}" . PHP_EOL);
    }
}
