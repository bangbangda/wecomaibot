<?php

declare(strict_types=1);

namespace WeComAiBot\Media;

/**
 * 文件下载结果
 */
class DownloadResult
{
    public function __construct(
        /** 文件二进制数据 */
        public readonly string $data,
        /** 文件名（从 Content-Disposition 解析，可能为 null） */
        public readonly ?string $filename = null,
    ) {
    }

    /**
     * 获取文件大小（字节）
     */
    public function size(): int
    {
        return strlen($this->data);
    }

    /**
     * 保存到指定路径
     *
     * @param string $path 文件路径
     * @throws \RuntimeException 保存失败
     */
    public function saveTo(string $path): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0755, true)) {
                throw new \RuntimeException("Failed to create directory: {$dir}");
            }
        }

        if (file_put_contents($path, $this->data) === false) {
            throw new \RuntimeException("Failed to save file: {$path}");
        }
    }
}
