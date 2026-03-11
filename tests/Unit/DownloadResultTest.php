<?php

declare(strict_types=1);

namespace WeComAiBot\Tests\Unit;

use WeComAiBot\Media\DownloadResult;
use WeComAiBot\Tests\TestCase;

class DownloadResultTest extends TestCase
{
    public function test_properties(): void
    {
        $result = new DownloadResult('binary data', 'photo.jpg');

        $this->assertSame('binary data', $result->data);
        $this->assertSame('photo.jpg', $result->filename);
    }

    public function test_size(): void
    {
        $result = new DownloadResult(str_repeat('x', 1024));

        $this->assertSame(1024, $result->size());
    }

    public function test_filename_nullable(): void
    {
        $result = new DownloadResult('data');

        $this->assertNull($result->filename);
    }

    public function test_save_to_creates_file(): void
    {
        $result = new DownloadResult('file content', 'test.txt');
        $path = sys_get_temp_dir() . '/wecomaibot_test_' . uniqid() . '.txt';

        try {
            $result->saveTo($path);
            $this->assertFileExists($path);
            $this->assertSame('file content', file_get_contents($path));
        } finally {
            @unlink($path);
        }
    }

    public function test_save_to_creates_directory(): void
    {
        $result = new DownloadResult('data');
        $dir = sys_get_temp_dir() . '/wecomaibot_test_' . uniqid();
        $path = $dir . '/sub/file.bin';

        try {
            $result->saveTo($path);
            $this->assertFileExists($path);
            $this->assertSame('data', file_get_contents($path));
        } finally {
            @unlink($path);
            @rmdir($dir . '/sub');
            @rmdir($dir);
        }
    }
}
