<?php

namespace Dybasedev\LunaPrototype\Content\Handlers;

use Dybasedev\LunaPrototype\Content\Handlers\BaseContentHandler;
use Dybasedev\LunaPrototype\Content\Models\Content;

class HtmlContentHandler extends BaseContentHandler
{
    /**
     * 获取处理器名称
     *
     * @return string
     */
    public function handlerName(): string
    {
        return 'HTML内容处理器';
    }

    /**
     * 获取处理器描述
     *
     * @return string
     */
    public function handlerDescription(): string
    {
        return '直接渲染HTML内容，支持富文本编辑器的内容';
    }

    /**
     * 渲染内容
     *
     * @param Content $content
     * @param array $options
     * @return array
     */
    public function render(Content $content, array $options = []): array
    {
        $currentVersion = $content->currentVersion;
        
        if (!$currentVersion) {
            return [
                'id' => $content->id,
                'name' => $content->name,
                'title' => $content->title,
                'content' => '',
                'metadata' => [],
            ];
        }

        // 过滤危险的HTML标签
        $filteredContent = $this->filterDangerousHtml($currentVersion->content);
        
        return [
            'id' => $content->id,
            'name' => $content->name,
            'title' => $content->title,
            'content' => $filteredContent,
            'metadata' => [
                'version' => $currentVersion->version_id,
                'created_at' => $currentVersion->created_at->toIso8601String(),
                'updated_at' => $currentVersion->updated_at->toIso8601String(),
            ],
        ];
    }

    /**
     * 预处理内容
     *
     * @param string $content
     * @param array $options
     * @return string
     */
    public function preprocess(string $content, array $options = []): string
    {
        // HTML内容通常不需要预处理
        return $content;
    }

    /**
     * 验证内容
     *
     * @param string $content
     * @return array
     */
    public function validate(string $content): array
    {
        $errors = [];

        // 检查危险标签
        $dangerousTags = ['script', 'iframe', 'object', 'embed'];
        foreach ($dangerousTags as $tag) {
            if (stripos($content, "<{$tag}") !== false) {
                $errors[] = "内容中包含不允许的标签: {$tag}";
            }
        }

        return $errors;
    }

    /**
     * 获取内容摘要
     *
     * @param string $content
     * @param int $length
     * @return string
     */
    public function getExcerpt(string $content, int $length = 200): string
    {
        // 去除HTML标签
        $text = strip_tags($content);
        
        // 去除多余的空白字符
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);
        
        // 截取指定长度
        if (mb_strlen($text) > $length) {
            return mb_substr($text, 0, $length) . '...';
        }
        
        return $text;
    }

    /**
     * 内容验证规则
     *
     * @return array
     */
    public function validationRules(): array
    {
        return [];
    }

    /**
     * 批量处理内容
     *
     * @param \Illuminate\Support\Collection $contents
     * @param array $options
     * @return \Illuminate\Support\Collection
     */
    public function batchProcess(\Illuminate\Support\Collection $contents, array $options = []): \Illuminate\Support\Collection
    {
        return $contents->map(function ($content) use ($options) {
            $rendered = $this->render($content, $options);
            return array_merge($rendered, [
                'id' => $content->id,
                'title' => $content->title,
                'name' => $content->name,
            ]);
        });
    }

    /**
     * 过滤危险的HTML标签
     *
     * @param string $html
     * @return string
     */
    protected function filterDangerousHtml(string $html): string
    {
        // 移除危险标签
        $dangerousTags = ['script', 'iframe', 'object', 'embed', 'form', 'input', 'button'];
        
        foreach ($dangerousTags as $tag) {
            // 移除开始和结束标签及其内容
            $html = preg_replace('/<' . $tag . '(\s[^>]*)?>(.*?)<\/' . $tag . '>/is', '', $html);
            // 移除自闭合标签
            $html = preg_replace('/<' . $tag . '(\s[^>]*)?\/?>/is', '', $html);
        }
        
        // 移除事件属性
        $html = preg_replace('/ on\w+="[^"]*"/i', '', $html);
        $html = preg_replace('/ on\w+=\'[^\']*\'/i', '', $html);
        $html = preg_replace('/ on\w+=[^ >]*/i', '', $html);
        
        // 移除javascript:协议
        $html = preg_replace('/href="javascript:[^"]*"/i', 'href="#"', $html);
        $html = preg_replace('/href=\'javascript:[^\']*\'/i', 'href=\'#\'', $html);
        
        return $html;
    }
}