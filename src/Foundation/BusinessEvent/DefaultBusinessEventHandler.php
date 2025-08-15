<?php

namespace Dybasedev\LunaPrototype\Foundation\BusinessEvent;

use Dybasedev\LunaPrototype\Foundation\BusinessEvent\Models\BusinessEvent;

/**
 * @property BusinessEvent $modelInstance
 */
class DefaultBusinessEventHandler extends BusinessEventHandler
{
    public function handlerName(): string
    {
        return '标准业务事件处理器';
    }

    public function handlerDescription(): string
    {
        return '标准业务事件处理器，对消息有基础的格式化功能';
    }

    public function formatPayloadToText(array $payload, ?string $format = null, array $context = []): string
    {
        if ($this->modelInstance?->formatter) {
            $formatter = $this->modelInstance->formatter;
            
            // 提取所有占位符
            preg_match_all('/{{([^}]+)}}/', $formatter, $matches);
            
            if (!empty($matches[1])) {
                $replaces = [];
                
                foreach ($matches[1] as $key) {
                    // 处理点号分隔的多层级路径
                    $value = $this->getNestedValue($payload, $key);
                    
                    // 将值转换为字符串
                    if (is_array($value) || is_object($value)) {
                        $value = json_encode($value, JSON_UNESCAPED_UNICODE);
                    } elseif (is_bool($value)) {
                        $value = $value ? 'true' : 'false';
                    } elseif (is_null($value)) {
                        $value = '';
                    }
                    
                    $replaces['{{' . $key . '}}'] = (string) $value;
                }
                
                return strtr($formatter, $replaces);
            }
            
            return $formatter;
        }

        return $this->modelInstance?->display_name ?: $this->modelInstance?->name ?: '';
    }
    
    /**
     * 从数组中获取嵌套值
     * 
     * @param array $data 数据数组
     * @param string $key 点号分隔的键路径，如 "user.profile.name"
     * @param mixed $default 默认值
     * @return mixed
     */
    protected function getNestedValue(array $data, string $key, mixed $default = null): mixed
    {
        // 如果键中没有点号，直接返回值
        if (!str_contains($key, '.')) {
            return $data[$key] ?? $default;
        }
        
        // 分割键路径
        $keys = explode('.', $key);
        $current = $data;
        
        foreach ($keys as $segment) {
            if (is_array($current) && array_key_exists($segment, $current)) {
                $current = $current[$segment];
            } elseif (is_object($current) && property_exists($current, $segment)) {
                $current = $current->$segment;
            } else {
                return $default;
            }
        }
        
        return $current;
    }

    public function formatPayloadToViewData(array $payload, ?string $format = null, array $context = []): ?array
    {
        // 默认格式化 handler 不支持，可继承实现
        return null;
    }

}
