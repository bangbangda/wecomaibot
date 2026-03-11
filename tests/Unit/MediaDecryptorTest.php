<?php

declare(strict_types=1);

namespace WeComAiBot\Tests\Unit;

use WeComAiBot\Media\MediaDecryptor;
use WeComAiBot\Tests\TestCase;

class MediaDecryptorTest extends TestCase
{
    /**
     * 生成一个有效的 Base64 编码的 32 字节 AES Key
     */
    private function makeAesKey(): string
    {
        return base64_encode(random_bytes(32));
    }

    /**
     * 用 AES-256-CBC + PKCS#7(32字节块) 加密数据，用于测试解密
     */
    private function encrypt(string $plaintext, string $aesKeyBase64): string
    {
        $key = base64_decode($aesKeyBase64);
        $iv = substr($key, 0, 16);

        // PKCS#7 填充至 32 字节倍数
        $blockSize = 32;
        $padLen = $blockSize - (strlen($plaintext) % $blockSize);
        $padded = $plaintext . str_repeat(chr($padLen), $padLen);

        // 加密（不使用 openssl 自动填充）
        $encrypted = openssl_encrypt(
            $padded,
            'aes-256-cbc',
            $key,
            OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING,
            $iv,
        );

        return $encrypted;
    }

    public function test_decrypt_simple_text(): void
    {
        $aesKey = $this->makeAesKey();
        $plaintext = 'Hello, World!';

        $encrypted = $this->encrypt($plaintext, $aesKey);
        $decrypted = MediaDecryptor::decrypt($encrypted, $aesKey);

        $this->assertSame($plaintext, $decrypted);
    }

    public function test_decrypt_binary_data(): void
    {
        $aesKey = $this->makeAesKey();
        // 模拟图片文件头
        $plaintext = "\xFF\xD8\xFF\xE0" . random_bytes(100);

        $encrypted = $this->encrypt($plaintext, $aesKey);
        $decrypted = MediaDecryptor::decrypt($encrypted, $aesKey);

        $this->assertSame($plaintext, $decrypted);
    }

    public function test_decrypt_exact_block_size(): void
    {
        $aesKey = $this->makeAesKey();
        // 恰好 32 字节（需要额外 32 字节填充）
        $plaintext = str_repeat('A', 32);

        $encrypted = $this->encrypt($plaintext, $aesKey);
        $decrypted = MediaDecryptor::decrypt($encrypted, $aesKey);

        $this->assertSame($plaintext, $decrypted);
    }

    public function test_decrypt_large_data(): void
    {
        $aesKey = $this->makeAesKey();
        $plaintext = random_bytes(10000);

        $encrypted = $this->encrypt($plaintext, $aesKey);
        $decrypted = MediaDecryptor::decrypt($encrypted, $aesKey);

        $this->assertSame($plaintext, $decrypted);
    }

    public function test_decrypt_empty_data_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Encrypted data is empty');

        MediaDecryptor::decrypt('', $this->makeAesKey());
    }

    public function test_decrypt_empty_key_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('AES key is empty');

        MediaDecryptor::decrypt('encrypted', '');
    }

    public function test_decrypt_invalid_key_length_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('must be 32 bytes');

        // 16 字节的 key（不是 32 字节）
        $shortKey = base64_encode(random_bytes(16));
        MediaDecryptor::decrypt('encrypted', $shortKey);
    }

    public function test_decrypt_wrong_key_throws(): void
    {
        $aesKey = $this->makeAesKey();
        $wrongKey = $this->makeAesKey();
        $plaintext = 'Hello';

        $encrypted = $this->encrypt($plaintext, $aesKey);

        $this->expectException(\RuntimeException::class);

        MediaDecryptor::decrypt($encrypted, $wrongKey);
    }

    public function test_decrypt_chinese_content(): void
    {
        $aesKey = $this->makeAesKey();
        $plaintext = '你好世界，这是一个中文测试';

        $encrypted = $this->encrypt($plaintext, $aesKey);
        $decrypted = MediaDecryptor::decrypt($encrypted, $aesKey);

        $this->assertSame($plaintext, $decrypted);
    }
}
