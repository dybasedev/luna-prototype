<?php

namespace Dybasedev\LunaPrototype\Content;

use Dybasedev\LunaPrototype\Foundation\Handler\BaseHandler;
use Dybasedev\LunaPrototype\Content\Models\Content;

abstract class ContentHandler extends BaseHandler
{
    /**
     * 渲染内容
     *
     * @param Content $content
     * @param array $options
     * @return array
     */
    abstract public function render(Content $content, array $options = []): array;

    /**
     * 预处理内容
     *
     * @param string $content
     * @param array $options
     * @return string
     */
    public function preprocess(string $content, array $options = []): string
    {
        return $content;
    }

    /**
     * 后处理内容
     *
     * @param array $rendered
     * @param array $options
     * @return array
     */
    public function postprocess(array $rendered, array $options = []): array
    {
        return $rendered;
    }

    /**
     * 验证内容
     *
     * @param string $content
     * @return array
     */
    public function validate(string $content): array
    {
        return [];
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
        $text = strip_tags($content);
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);
        
        if (mb_strlen($text) > $length) {
            return mb_substr($text, 0, $length) . '...';
        }
        
        return $text;
    }

    /**
     * 获取内容字数
     *
     * @param string $content
     * @return int
     */
    public function getWordCount(string $content): int
    {
        $text = strip_tags($content);
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);
        
        return str_word_count($text);
    }

    /**
     * 获取内容的元信息
     *
     * @param Content $content
     * @return array
     */
    public function getMetaInfo(Content $content): array
    {
        $currentVersion = $content->currentVersion;
        
        if (!$currentVersion) {
            return [];
        }

        return [
            'word_count' => $this->getWordCount($currentVersion->content),
            'excerpt' => $this->getExcerpt($currentVersion->content),
            'version' => $currentVersion->version_id,
            'created_at' => $currentVersion->created_at,
            'updated_at' => $currentVersion->updated_at,
        ];
    }

    /**
     * 批量处理内容
     *
     * @param \Illuminate\Support\Collection|array $contents
     * @param array $options
     * @return array
     */
    public function batchProcess($contents, array $options = []): array
    {
        $results = [];
        
        foreach ($contents as $content) {
            $rendered = $this->render($content, $options);
            
            $results[] = array_merge($rendered, [
                'id' => $content->id,
                'name' => $content->name,
                'title' => $content->title,
            ]);
        }
        
        return $results;
    }
}