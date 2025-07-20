<?php

namespace Dybasedev\LunaPrototype\Trade;

use Illuminate\Support\Collection;

/**
 * 交易上下文
 * 
 * 携带交易过程中的上下文信息，如渠道、活动、设备信息等。
 * 用于在交易流程的各个环节传递必要的环境信息。
 * 
 * @package Dybasedev\LunaPrototype\Trade
 * @author Luna Prototype Team
 * @since 1.0.0
 */
class TransactionContext
{
    /**
     * @var array 上下文数据
     */
    protected array $data = [];
    
    /**
     * @var Collection 连接器集合
     */
    protected Collection $connectors;
    
    /**
     * @var array 参数集合
     */
    protected array $parameters = [];
    
    public function __construct(array $data = [])
    {
        $this->data = $data;
        $this->connectors = collect();
    }
    
    /**
     * 创建实例
     * 
     * @param array $data
     * @return static
     */
    public static function make(array $data = []): static
    {
        return new static($data);
    }
    
    /**
     * 设置渠道
     * 
     * @param string $channel
     * @param array $channelData
     * @return self
     */
    public function fromChannel(string $channel, array $channelData = []): self
    {
        $this->data['channel'] = $channel;
        $this->data['channel_data'] = $channelData;
        return $this;
    }
    
    /**
     * 设置活动信息
     * 
     * @param string $campaignId
     * @param array $campaignData
     * @return self
     */
    public function fromCampaign(string $campaignId, array $campaignData = []): self
    {
        $this->data['campaign_id'] = $campaignId;
        $this->data['campaign_data'] = $campaignData;
        return $this;
    }
    
    /**
     * 设置设备信息
     * 
     * @param array $device
     * @return self
     */
    public function withDevice(array $device): self
    {
        $this->data['device'] = $device;
        return $this;
    }
    
    /**
     * 设置IP地址
     * 
     * @param string $ip
     * @return self
     */
    public function withIp(string $ip): self
    {
        $this->data['ip'] = $ip;
        return $this;
    }
    
    /**
     * 设置用户代理
     * 
     * @param string $userAgent
     * @return self
     */
    public function withUserAgent(string $userAgent): self
    {
        $this->data['user_agent'] = $userAgent;
        return $this;
    }
    
    /**
     * 设置来源
     * 
     * @param string $source
     * @param array $sourceData
     * @return self
     */
    public function fromSource(string $source, array $sourceData = []): self
    {
        $this->data['source'] = $source;
        $this->data['source_data'] = $sourceData;
        return $this;
    }
    
    /**
     * 添加连接器
     * 
     * @param string $name
     * @param callable $connector
     * @return self
     */
    public function addConnector(string $name, callable $connector): self
    {
        $this->connectors->put($name, $connector);
        return $this;
    }
    
    /**
     * 设置参数
     * 
     * @param string $key
     * @param mixed $value
     * @return self
     */
    public function setParameter(string $key, mixed $value): self
    {
        $this->parameters[$key] = $value;
        return $this;
    }
    
    /**
     * 批量设置参数
     * 
     * @param array $parameters
     * @return self
     */
    public function withParameters(array $parameters): self
    {
        $this->parameters = array_merge($this->parameters, $parameters);
        return $this;
    }
    
    /**
     * 获取参数
     * 
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getParameter(string $key, mixed $default = null): mixed
    {
        return data_get($this->parameters, $key, $default);
    }
    
    /**
     * 获取所有参数
     * 
     * @return array
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }
    
    /**
     * 执行连接器
     * 
     * @param string $name
     * @param mixed ...$args
     * @return mixed
     */
    public function executeConnector(string $name, mixed ...$args): mixed
    {
        if (!$this->connectors->has($name)) {
            throw new \RuntimeException("Connector '{$name}' not found");
        }
        
        $connector = $this->connectors->get($name);
        return $connector(...$args);
    }
    
    /**
     * 检查是否有连接器
     * 
     * @param string $name
     * @return bool
     */
    public function hasConnector(string $name): bool
    {
        return $this->connectors->has($name);
    }
    
    /**
     * 获取上下文数据
     * 
     * @param string|null $key
     * @param mixed $default
     * @return mixed
     */
    public function get(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->data;
        }
        
        return data_get($this->data, $key, $default);
    }
    
    /**
     * 设置上下文数据
     * 
     * @param string $key
     * @param mixed $value
     * @return self
     */
    public function set(string $key, mixed $value): self
    {
        data_set($this->data, $key, $value);
        return $this;
    }
    
    /**
     * 合并上下文数据
     * 
     * @param array $data
     * @return self
     */
    public function merge(array $data): self
    {
        $this->data = array_merge($this->data, $data);
        return $this;
    }
    
    /**
     * 转换为数组
     * 
     * @return array
     */
    public function toArray(): array
    {
        return [
            'data' => $this->data,
            'parameters' => $this->parameters,
            'connectors' => $this->connectors->keys()->all(),
        ];
    }
    
    /**
     * 获取渠道
     * 
     * @return string|null
     */
    public function getChannel(): ?string
    {
        return $this->data['channel'] ?? null;
    }
    
    /**
     * 获取活动ID
     * 
     * @return string|null
     */
    public function getCampaignId(): ?string
    {
        return $this->data['campaign_id'] ?? null;
    }
    
    /**
     * 获取来源
     * 
     * @return string|null
     */
    public function getSource(): ?string
    {
        return $this->data['source'] ?? null;
    }
    
    /**
     * 获取设备信息
     * 
     * @return array|null
     */
    public function getDevice(): ?array
    {
        return $this->data['device'] ?? null;
    }
}