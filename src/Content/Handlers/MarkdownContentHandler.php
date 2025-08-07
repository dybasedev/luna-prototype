<?php

namespace Dybasedev\LunaPrototype\Content\Handlers;

use Dybasedev\LunaPrototype\Content\Handlers\BaseContentHandler;
use Dybasedev\LunaPrototype\Content\Models\Content;

class MarkdownContentHandler extends BaseContentHandler
{
    /**
     * 获取处理器名称
     *
     * @return string
     */
    public function handlerName(): string
    {
        return 'Markdown内容处理器';
    }

    /**
     * 获取处理器描述
     *
     * @return string
     */
    public function handlerDescription(): string
    {
        return '支持Markdown格式的内容，自动转换为HTML';
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
                'html' => '',
                'markdown' => '',
                'metadata' => [],
            ];
        }

        $markdown = $currentVersion->content;
        $html = $this->convertMarkdownToHtml($markdown);

        return [
            'id' => $content->id,
            'name' => $content->name,
            'title' => $content->title,
            'content' => $html,
            'raw_content' => $markdown,
            'html' => $html,
            'markdown' => $markdown,
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
        // 标准化换行符
        $content = str_replace(["\r\n", "\r"], "\n", $content);
        
        // 去除尾部空白
        $lines = explode("\n", $content);
        $lines = array_map('rtrim', $lines);
        
        return implode("\n", $lines);
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

        // 检查内容长度
        if (strlen($content) > 1000000) { // 1MB
            $errors[] = 'Markdown内容超过最大长度限制';
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
        // 移除Markdown语法
        $text = $content;
        
        // 移除标题标记
        $text = preg_replace('/^#+\s+/m', '', $text);
        
        // 移除链接
        $text = preg_replace('/\[([^\]]+)\]\([^\)]+\)/', '$1', $text);
        
        // 移除图片
        $text = preg_replace('/!\[([^\]]*)\]\([^\)]+\)/', '', $text);
        
        // 移除强调标记
        $text = preg_replace('/(\*\*|__)(.*?)\1/', '$2', $text);
        $text = preg_replace('/(\*|_)(.*?)\1/', '$2', $text);
        
        // 移除代码块
        $text = preg_replace('/```[^`]*```/s', '', $text);
        $text = preg_replace('/`([^`]+)`/', '$1', $text);
        
        // 移除引用标记
        $text = preg_replace('/^>\s+/m', '', $text);
        
        // 移除列表标记
        $text = preg_replace('/^[\*\-\+]\s+/m', '', $text);
        $text = preg_replace('/^\d+\.\s+/m', '', $text);
        
        // 清理空白
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);
        
        // 截取指定长度
        if (mb_strlen($text) > $length) {
            return mb_substr($text, 0, $length) . '...';
        }
        
        return $text;
    }

    /**
     * 将Markdown转换为HTML
     *
     * @param string $markdown
     * @return string
     */
    protected function convertMarkdownToHtml(string $markdown): string
    {
        // 简单的Markdown到HTML转换实现
        $html = $markdown;
        
        // 转换标题
        $html = preg_replace('/^###### (.+)$/m', '<h6>$1</h6>', $html);
        $html = preg_replace('/^##### (.+)$/m', '<h5>$1</h5>', $html);
        $html = preg_replace('/^#### (.+)$/m', '<h4>$1</h4>', $html);
        $html = preg_replace('/^### (.+)$/m', '<h3>$1</h3>', $html);
        $html = preg_replace('/^## (.+)$/m', '<h2>$1</h2>', $html);
        $html = preg_replace('/^# (.+)$/m', '<h1>$1</h1>', $html);
        
        // 转换强调
        $html = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $html);
        $html = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $html);
        
        // 转换链接
        $html = preg_replace('/\[([^\]]+)\]\(([^\)]+)\)/', '<a href="$2">$1</a>', $html);
        
        // 转换段落
        $paragraphs = explode("\n\n", $html);
        $paragraphs = array_map(function($p) {
            $p = trim($p);
            if ($p && !preg_match('/^<[hH][1-6]>/', $p)) {
                return "<p>$p</p>";
            }
            return $p;
        }, $paragraphs);
        
        return implode("\n", $paragraphs);
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
}