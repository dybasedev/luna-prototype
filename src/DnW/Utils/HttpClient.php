<?php

namespace Dybasedev\LunaPrototype\DnW\Utils;

use Dybasedev\LunaPrototype\Foundation\Exception\LunaException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * HTTP 客户端工具类
 * 
 * 封装常见的 HTTP 请求操作
 */
class HttpClient
{
    /**
     * 配置选项
     */
    protected array $config = [];

    /**
     * 构造函数
     */
    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'timeout' => 30,
            'headers' => [],
            'asJson' => true,
            'retries' => 0,
            'sleep' => 1000,
        ], $config);
    }

    /**
     * 实例方法：发起 POST 请求
     */
    public function post(string $url, array $data = [], array $extraHeaders = []): array
    {
        $headers = array_merge($this->config['headers'], $extraHeaders);
        
        $response = static::doPost(
            $url,
            $data,
            $headers,
            $this->config['timeout'],
            $this->config['asJson']
        );

        if (!$response->successful()) {
            throw LunaException::create('HTTP request failed')
                ->withData([
                    'status' => $response->status(),
                    'body' => $response->body(),
                ])
                ->withHttpStatus($response->status());
        }

        return $response->json() ?? [];
    }

    /**
     * 实例方法：发起 GET 请求
     */
    public function get(string $url, array $query = [], array $extraHeaders = []): array
    {
        $headers = array_merge($this->config['headers'], $extraHeaders);
        
        $response = static::doGet(
            $url,
            $query,
            $headers,
            $this->config['timeout']
        );

        if (!$response->successful()) {
            throw LunaException::create('HTTP request failed')
                ->withData([
                    'status' => $response->status(),
                    'body' => $response->body(),
                ])
                ->withHttpStatus($response->status());
        }

        return $response->json() ?? [];
    }

    /**
     * 实例方法：发起表单 POST 请求
     */
    public function postForm(string $url, array $data = [], array $extraHeaders = []): array|string
    {
        $headers = array_merge($this->config['headers'], $extraHeaders);
        
        $response = static::doPost(
            $url,
            $data,
            $headers,
            $this->config['timeout'],
            false // asJson = false for form submission
        );

        if (!$response->successful()) {
            throw LunaException::create('HTTP request failed')
                ->withData([
                    'status' => $response->status(),
                    'body' => $response->body(),
                ])
                ->withHttpStatus($response->status());
        }

        // Try to decode JSON, if it fails return the body as string
        $json = $response->json();
        return $json !== null ? $json : $response->body();
    }
    /**
     * 静态方法：发起 POST 请求
     */
    public static function doPost(
        string $url,
        array $data = [],
        array $headers = [],
        int $timeout = 30,
        bool $asJson = true
    ): Response {
        $client = Http::timeout($timeout);

        if (!empty($headers)) {
            $client = $client->withHeaders($headers);
        }

        Log::info('DnW HTTP Request', [
            'method' => 'POST',
            'url' => $url,
            'data' => $data,
            'headers' => $headers,
        ]);

        if ($asJson) {
            $response = $client->post($url, $data);
        } else {
            $response = $client->asForm()->post($url, $data);
        }

        Log::info('DnW HTTP Response', [
            'status' => $response->status(),
            'body' => $response->body(),
            'headers' => $response->headers(),
        ]);

        return $response;
    }

    /**
     * 静态方法：发起 GET 请求
     */
    public static function doGet(
        string $url,
        array $query = [],
        array $headers = [],
        int $timeout = 30
    ): Response {
        $client = Http::timeout($timeout);

        if (!empty($headers)) {
            $client = $client->withHeaders($headers);
        }

        Log::info('DnW HTTP Request', [
            'method' => 'GET',
            'url' => $url,
            'query' => $query,
            'headers' => $headers,
        ]);

        $response = $client->get($url, $query);

        Log::info('DnW HTTP Response', [
            'status' => $response->status(),
            'body' => $response->body(),
            'headers' => $response->headers(),
        ]);

        return $response;
    }

    /**
     * 发起带重试的请求
     */
    public static function postWithRetry(
        string $url,
        array $data = [],
        array $headers = [],
        int $timeout = 30,
        int $retries = 3,
        int $sleep = 1000,
        bool $asJson = true
    ): Response {
        $client = Http::timeout($timeout)->retry($retries, $sleep);

        if (!empty($headers)) {
            $client = $client->withHeaders($headers);
        }

        Log::info('DnW HTTP Request with Retry', [
            'method' => 'POST',
            'url' => $url,
            'data' => $data,
            'headers' => $headers,
            'retries' => $retries,
        ]);

        if ($asJson) {
            $response = $client->post($url, $data);
        } else {
            $response = $client->asForm()->post($url, $data);
        }

        Log::info('DnW HTTP Response', [
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        return $response;
    }

    /**
     * 验证回调请求的来源 IP
     */
    public static function validateCallbackIP(string $clientIP, array $allowedIPs): bool
    {
        if (empty($allowedIPs)) {
            return true;
        }

        foreach ($allowedIPs as $allowedIP) {
            if (static::isIPInRange($clientIP, $allowedIP)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 检查 IP 是否在指定范围内
     */
    protected static function isIPInRange(string $ip, string $range): bool
    {
        if (strpos($range, '/') === false) {
            return $ip === $range;
        }

        list($rangeIP, $netmask) = explode('/', $range);
        
        $rangeDecimal = ip2long($rangeIP);
        $ipDecimal = ip2long($ip);
        $wildcardDecimal = pow(2, (32 - $netmask)) - 1;
        $netmaskDecimal = ~ $wildcardDecimal;

        return ($ipDecimal & $netmaskDecimal) === ($rangeDecimal & $netmaskDecimal);
    }

    /**
     * 获取请求的真实 IP
     */
    public static function getRealIP(): string
    {
        $headers = [
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'HTTP_CF_CONNECTING_IP',
            'HTTP_CLIENT_IP',
            'REMOTE_ADDR'
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                $ip = trim($ips[0]);
                
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    /**
     * 构建查询字符串
     */
    public static function buildQuery(array $data, bool $excludeEmpty = true): string
    {
        if ($excludeEmpty) {
            $data = array_filter($data, function ($value) {
                return $value !== null && $value !== '';
            });
        }

        return http_build_query($data, '', '&', PHP_QUERY_RFC3986);
    }
}