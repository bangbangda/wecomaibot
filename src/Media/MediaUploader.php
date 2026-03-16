<?php

declare(strict_types=1);

namespace WeComAiBot\Media;

use WeComAiBot\Connection\WsClient;
use WeComAiBot\Protocol\FrameBuilder;
use WeComAiBot\Support\LoggerInterface;

/**
 * 临时素材上传器
 *
 * 通过 WebSocket 分片上传文件，获取 media_id 用于发送图片/文件/语音/视频消息。
 *
 * 上传流程（3 步异步）：
 * 1. aibot_upload_media_init → 获取 upload_id
 * 2. aibot_upload_media_chunk × N → 逐片上传（每片 ≤ 512KB）
 * 3. aibot_upload_media_finish → 获取 media_id
 *
 * 约束：
 * - 上传会话有效期 30 分钟
 * - 单个分片 ≤ 512KB（Base64 编码前），最多 100 个分片
 * - 临时素材有效期 3 天
 * - 图片/语音 ≤ 2MB，视频 ≤ 10MB，普通文件 ≤ 20MB
 * - 图片支持 png、jpg/jpeg、gif；语音支持 amr；视频支持 mp4
 */
class MediaUploader
{
    /** 单个分片最大字节数（Base64 编码前） */
    private const CHUNK_SIZE = 512 * 1024;

    /**
     * 上传文件并获取 media_id
     *
     * @param WsClient        $client     WebSocket 客户端
     * @param string          $type       文件类型：image, voice, video, file
     * @param string          $filePath   本地文件路径
     * @param LoggerInterface $logger     日志
     * @param callable        $onComplete 完成回调：fn(?string $mediaId, ?string $error) => void
     */
    public function upload(
        WsClient $client,
        string $type,
        string $filePath,
        LoggerInterface $logger,
        callable $onComplete,
    ): void {
        $content = file_get_contents($filePath);
        if ($content === false) {
            $onComplete(null, "Failed to read file: {$filePath}");
            return;
        }

        $filename = basename($filePath);
        $totalSize = strlen($content);
        $totalChunks = (int) ceil($totalSize / self::CHUNK_SIZE);
        $md5 = md5($content);

        if ($totalChunks > 100) {
            $onComplete(null, "File too large: {$totalChunks} chunks exceeds max 100");
            return;
        }

        $logger->info("Uploading {$type}: {$filename} ({$totalSize} bytes, {$totalChunks} chunks)");

        // Step 1: Init
        $initFrame = FrameBuilder::uploadMediaInit($type, $filename, $totalSize, $totalChunks, $md5);
        $initData = json_decode($initFrame, true);
        $initReqId = $initData['headers']['req_id'];

        $client->sendWithResponse($initFrame, $initReqId, function (array $response) use (
            $client, $content, $totalChunks, $logger, $onComplete
        ) {
            $errcode = $response['errcode'] ?? -1;
            if ($errcode !== 0) {
                $errmsg = $response['errmsg'] ?? 'unknown';
                $onComplete(null, "Upload init failed: [{$errcode}] {$errmsg}");
                return;
            }

            $uploadId = $response['body']['upload_id'] ?? '';
            if ($uploadId === '') {
                $onComplete(null, 'Upload init returned empty upload_id');
                return;
            }

            $logger->debug("Upload init OK, upload_id={$uploadId}");

            // Step 2: Send chunks
            $this->sendChunks($client, $uploadId, $content, $totalChunks, 0, $logger, $onComplete);
        });
    }

    /**
     * 递归发送分片（每片等待响应后再发下一片）
     */
    private function sendChunks(
        WsClient $client,
        string $uploadId,
        string $content,
        int $totalChunks,
        int $chunkIndex,
        LoggerInterface $logger,
        callable $onComplete,
    ): void {
        if ($chunkIndex >= $totalChunks) {
            // 所有分片发送完毕，Step 3: Finish
            $this->finishUpload($client, $uploadId, $logger, $onComplete);
            return;
        }

        $chunk = substr($content, $chunkIndex * self::CHUNK_SIZE, self::CHUNK_SIZE);
        $base64Data = base64_encode($chunk);

        $chunkFrame = FrameBuilder::uploadMediaChunk($uploadId, $chunkIndex, $base64Data);
        $chunkData = json_decode($chunkFrame, true);
        $chunkReqId = $chunkData['headers']['req_id'];

        $client->sendWithResponse($chunkFrame, $chunkReqId, function (array $response) use (
            $client, $uploadId, $content, $totalChunks, $chunkIndex, $logger, $onComplete
        ) {
            $errcode = $response['errcode'] ?? -1;
            if ($errcode !== 0) {
                $errmsg = $response['errmsg'] ?? 'unknown';
                $onComplete(null, "Chunk {$chunkIndex} failed: [{$errcode}] {$errmsg}");
                return;
            }

            $logger->debug("Chunk {$chunkIndex}/{$totalChunks} uploaded");

            // 发送下一个分片
            $this->sendChunks($client, $uploadId, $content, $totalChunks, $chunkIndex + 1, $logger, $onComplete);
        });
    }

    /**
     * 完成上传，获取 media_id
     */
    private function finishUpload(
        WsClient $client,
        string $uploadId,
        LoggerInterface $logger,
        callable $onComplete,
    ): void {
        $finishFrame = FrameBuilder::uploadMediaFinish($uploadId);
        $finishData = json_decode($finishFrame, true);
        $finishReqId = $finishData['headers']['req_id'];

        $client->sendWithResponse($finishFrame, $finishReqId, function (array $response) use (
            $logger, $onComplete
        ) {
            $errcode = $response['errcode'] ?? -1;
            if ($errcode !== 0) {
                $errmsg = $response['errmsg'] ?? 'unknown';
                $onComplete(null, "Upload finish failed: [{$errcode}] {$errmsg}");
                return;
            }

            $mediaId = $response['body']['media_id'] ?? '';
            if ($mediaId === '') {
                $onComplete(null, 'Upload finish returned empty media_id');
                return;
            }

            $logger->info("Upload complete, media_id={$mediaId}");
            $onComplete($mediaId, null);
        });
    }
}
