<?php

namespace Dybasedev\LunaPrototype\DnW\Utils;

/**
 * 签名验证工具类
 * 
 * 提供常见的签名验证算法
 */
class SignatureValidator
{
    /**
     * 密钥
     */
    protected string $secret;

    /**
     * 默认算法
     */
    protected string $algorithm = 'sha256';

    /**
     * 构造函数
     */
    public function __construct(string $secret, string $algorithm = 'sha256')
    {
        $this->secret = $secret;
        $this->algorithm = $algorithm;
    }

    /**
     * 生成签名
     */
    public function generateSignature(array $data, array $excludeFields = []): string
    {
        return match($this->algorithm) {
            'md5' => static::generateMD5($data, $this->secret, $excludeFields),
            'sha1' => static::generateSHA1($data, $this->secret, $excludeFields),
            'sha256' => static::generateSHA256($data, $this->secret, $excludeFields),
            'hmac_sha256' => static::generateHMACSign($data, $this->secret, $excludeFields),
            default => static::generateSHA256($data, $this->secret, $excludeFields),
        };
    }

    /**
     * 验证签名
     */
    public function validateSignature(array $data, string $signature, array $excludeFields = []): bool
    {
        return match($this->algorithm) {
            'md5' => static::validateMD5($data, $signature, $this->secret, $excludeFields),
            'sha1' => static::validateSHA1($data, $signature, $this->secret, $excludeFields),
            'sha256' => static::validateSHA256($data, $signature, $this->secret, $excludeFields),
            'hmac_sha256' => static::validateHMACSign($data, $signature, $this->secret, $excludeFields),
            default => static::validateSHA256($data, $signature, $this->secret, $excludeFields),
        };
    }

    /**
     * 生成带时间戳的签名
     */
    public function generateSignatureWithTimestamp(array $data, array $excludeFields = []): array
    {
        $timestamp = time();
        $data['timestamp'] = $timestamp;
        $signature = $this->generateSignature($data, $excludeFields);
        
        return [
            'signature' => $signature,
            'timestamp' => $timestamp,
        ];
    }

    /**
     * 验证带时间戳的签名
     */
    public function validateSignatureWithTimestamp(array $data, array $signatureData, int $maxAge = 300, array $excludeFields = []): bool
    {
        // 验证时间戳
        $timestamp = $signatureData['timestamp'] ?? 0;
        if (abs(time() - $timestamp) > $maxAge) {
            return false;
        }
        
        // 添加时间戳到数据中
        $data['timestamp'] = $timestamp;
        
        // 验证签名
        return $this->validateSignature($data, $signatureData['signature'] ?? '', $excludeFields);
    }
    /**
     * MD5 签名验证
     */
    public static function validateMD5(array $data, string $signature, string $key, array $excludeFields = []): bool
    {
        $signData = static::prepareSignData($data, $excludeFields);
        $expectedSignature = md5($signData . $key);
        
        return hash_equals(strtolower($expectedSignature), strtolower($signature));
    }

    /**
     * SHA1 签名验证
     */
    public static function validateSHA1(array $data, string $signature, string $key, array $excludeFields = []): bool
    {
        $signData = static::prepareSignData($data, $excludeFields);
        $expectedSignature = sha1($signData . $key);
        
        return hash_equals(strtolower($expectedSignature), strtolower($signature));
    }

    /**
     * SHA256 签名验证
     */
    public static function validateSHA256(array $data, string $signature, string $key, array $excludeFields = []): bool
    {
        $signData = static::prepareSignData($data, $excludeFields);
        $expectedSignature = hash('sha256', $signData . $key);
        
        return hash_equals(strtolower($expectedSignature), strtolower($signature));
    }

    /**
     * HMAC-SHA256 签名验证
     */
    public static function validateHMACSign(array $data, string $signature, string $key, array $excludeFields = []): bool
    {
        $signData = static::prepareSignData($data, $excludeFields);
        $expectedSignature = hash_hmac('sha256', $signData, $key);
        
        return hash_equals(strtolower($expectedSignature), strtolower($signature));
    }

    /**
     * RSA 签名验证
     */
    public static function validateRSA(string $data, string $signature, string $publicKey, int $algorithm = OPENSSL_ALGO_SHA256): bool
    {
        $signature = base64_decode($signature);
        $result = openssl_verify($data, $signature, $publicKey, $algorithm);
        
        return $result === 1;
    }

    /**
     * 准备签名数据
     */
    protected static function prepareSignData(array $data, array $excludeFields = []): string
    {
        // 移除排除字段
        foreach ($excludeFields as $field) {
            unset($data[$field]);
        }

        // 移除空值
        $data = array_filter($data, function ($value) {
            return $value !== null && $value !== '';
        });

        // 按键名排序
        ksort($data);

        // 构建查询字符串
        return http_build_query($data, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * 生成 MD5 签名
     */
    public static function generateMD5(array $data, string $key, array $excludeFields = []): string
    {
        $signData = static::prepareSignData($data, $excludeFields);
        return md5($signData . $key);
    }

    /**
     * 生成 SHA1 签名
     */
    public static function generateSHA1(array $data, string $key, array $excludeFields = []): string
    {
        $signData = static::prepareSignData($data, $excludeFields);
        return sha1($signData . $key);
    }

    /**
     * 生成 SHA256 签名
     */
    public static function generateSHA256(array $data, string $key, array $excludeFields = []): string
    {
        $signData = static::prepareSignData($data, $excludeFields);
        return hash('sha256', $signData . $key);
    }

    /**
     * 生成 HMAC-SHA256 签名
     */
    public static function generateHMACSign(array $data, string $key, array $excludeFields = []): string
    {
        $signData = static::prepareSignData($data, $excludeFields);
        return hash_hmac('sha256', $signData, $key);
    }

    /**
     * 获取支持的签名算法列表
     */
    public static function getSupportedAlgorithms(): array
    {
        return [
            'md5' => 'MD5',
            'sha1' => 'SHA1', 
            'sha256' => 'SHA256',
            'hmac_sha256' => 'HMAC-SHA256',
            'rsa' => 'RSA',
        ];
    }
}