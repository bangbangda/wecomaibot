<?php

declare(strict_types=1);

namespace WeComAiBot\Media;

use WeComAiBot\Support\LoggerInterface;

/**
 * 企微多媒体文件下载器
 *
 * 下载企微消息中的图片和文件，支持自动解密。
 * URL 有效期 5 分钟，过期后需要重新获取。
 */
class MediaDownloader
{
    public function __construct(
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * 下载并解密文件
     *
     * @param string      $url    文件下载 URL
     * @param string|null $aesKey Base64 编码的 AES Key，为 null 时不解密
     * @return DownloadResult 下载结果（含二进制数据和文件名）
     *
     * @throws \RuntimeException 下载或解密失败
     */
    public function download(string $url, ?string $aesKey = null): DownloadResult
    {
        $this->logger?->info('Downloading media file...');

        // 下载
        $context = stream_context_create([
            'http' => [
                'timeout' => 30,
                'follow_location' => true,
                'max_redirects' => 3,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $data = @file_get_contents($url, false, $context);

        if ($data === false) {
            $error = error_get_last();
            throw new \RuntimeException('File download failed: ' . ($error['message'] ?? 'unknown error'));
        }

        // 从响应头解析文件名
        $filename = $this->parseFilename($http_response_header ?? []);

        $this->logger?->info('File downloaded: ' . strlen($data) . ' bytes');

        // 解密
        if ($aesKey !== null && $aesKey !== '') {
            $this->logger?->info('Decrypting file...');
            $data = MediaDecryptor::decrypt($data, $aesKey);
            $this->logger?->info('File decrypted: ' . strlen($data) . ' bytes');
        }

        return new DownloadResult($data, $filename);
    }

    /**
     * 下载并保存到文件
     *
     * @param string      $url      文件下载 URL
     * @param string      $savePath 保存路径（目录或完整文件路径）
     * @param string|null $aesKey   Base64 编码的 AES Key
     * @return string 实际保存的文件路径
     *
     * @throws \RuntimeException 下载、解密或保存失败
     */
    public function downloadToFile(string $url, string $savePath, ?string $aesKey = null): string
    {
        $result = $this->download($url, $aesKey);

        // 如果 savePath 是目录，拼接文件名
        if (is_dir($savePath)) {
            $filename = $result->filename ?: ('media_' . time() . '_' . bin2hex(random_bytes(4)));
            $savePath = rtrim($savePath, '/') . '/' . $filename;
        }

        // 确保目录存在
        $dir = dirname($savePath);
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0755, true)) {
                throw new \RuntimeException("Failed to create directory: {$dir}");
            }
        }

        if (file_put_contents($savePath, $result->data) === false) {
            throw new \RuntimeException("Failed to save file: {$savePath}");
        }

        $this->logger?->info("File saved to: {$savePath}");

        return $savePath;
    }

    /**
     * 从 HTTP 响应头解析文件名
     */
    private function parseFilename(array $headers): ?string
    {
        foreach ($headers as $header) {
            if (stripos($header, 'Content-Disposition') === false) {
                continue;
            }

            // RFC 5987: filename*=UTF-8''encoded_name
            if (preg_match("/filename\\*=UTF-8''([^;\\s]+)/i", $header, $matches)) {
                return urldecode($matches[1]);
            }

            // 标准格式: filename="name" 或 filename=name
            if (preg_match('/filename="?([^";\\s]+)"?/i', $header, $matches)) {
                return urldecode($matches[1]);
            }
        }

        return null;
    }
}
