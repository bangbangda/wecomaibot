<?php

declare(strict_types=1);

namespace WeComAiBot\Media;

/**
 * 企微多媒体文件解密器
 *
 * 企微传输的图片和文件经过 AES-256-CBC 加密，需要用消息中的 aeskey 解密。
 *
 * 加密规则：
 * - 算法：AES-256-CBC
 * - Key：aeskey 经 Base64 解码后的 32 字节
 * - IV：Key 的前 16 字节
 * - 填充：PKCS#7，填充至 32 字节的倍数
 */
class MediaDecryptor
{
    private const CIPHER = 'aes-256-cbc';
    private const PKCS7_BLOCK_SIZE = 32;

    /**
     * 解密文件内容
     *
     * @param string $encryptedData 加密的二进制数据
     * @param string $aesKey        Base64 编码的 AES Key
     * @return string 解密后的二进制数据
     *
     * @throws \InvalidArgumentException 参数无效
     * @throws \RuntimeException         解密失败
     */
    public static function decrypt(string $encryptedData, string $aesKey): string
    {
        if ($encryptedData === '') {
            throw new \InvalidArgumentException('Encrypted data is empty');
        }
        if ($aesKey === '') {
            throw new \InvalidArgumentException('AES key is empty');
        }

        // Base64 解码 aesKey → 32 字节密钥
        $key = base64_decode($aesKey, true);
        if ($key === false || strlen($key) !== 32) {
            throw new \InvalidArgumentException('Invalid AES key: must be 32 bytes after Base64 decode');
        }

        // IV = 密钥前 16 字节
        $iv = substr($key, 0, 16);

        // 解密（关闭自动 padding，手动处理 PKCS#7 的 32 字节块）
        $decrypted = openssl_decrypt(
            $encryptedData,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING,
            $iv,
        );

        if ($decrypted === false) {
            throw new \RuntimeException('AES decryption failed: ' . openssl_error_string());
        }

        // 手动去除 PKCS#7 填充（32 字节块）
        return self::removePkcs7Padding($decrypted);
    }

    /**
     * 去除 PKCS#7 填充
     *
     * @param string $data 解密后带填充的数据
     * @return string 去除填充后的原始数据
     *
     * @throws \RuntimeException 填充无效
     */
    private static function removePkcs7Padding(string $data): string
    {
        $length = strlen($data);
        if ($length === 0) {
            throw new \RuntimeException('Invalid PKCS#7 padding: data is empty');
        }

        $padLen = ord($data[$length - 1]);

        if ($padLen < 1 || $padLen > self::PKCS7_BLOCK_SIZE || $padLen > $length) {
            throw new \RuntimeException("Invalid PKCS#7 padding value: {$padLen}");
        }

        // 验证所有填充字节一致
        for ($i = $length - $padLen; $i < $length; $i++) {
            if (ord($data[$i]) !== $padLen) {
                throw new \RuntimeException('Invalid PKCS#7 padding: bytes mismatch');
            }
        }

        return substr($data, 0, $length - $padLen);
    }
}
