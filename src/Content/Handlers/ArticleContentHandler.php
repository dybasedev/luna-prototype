<?php

namespace Dybasedev\LunaPrototype\Content\Handlers;

use Dybasedev\LunaPrototype\Content\Models\Content;
use Illuminate\Support\Str;

class ArticleContentHandler extends BaseContentHandler
{
    /**
     * 获取处理器名称
     *
     * @return string
     */
    public function handlerName(): string
    {
        return '文章内容处理器';
    }

    /**
     * 获取处理器描述
     *
     * @return string
     */
    public function handlerDescription(): string
    {
        return '用于处理文章类型的内容，支持富文本编辑、摘要生成等功能';
    }

    /**
     * 内容渲染
     *
     * @param Content $content
     * @param array $options
     * @return array
     */
    public function render(Content $content, array $options = []): array
    {
        $data = [
            'id' => $content->id,
            'name' => $content->name,
            'title' => $content->title,
            'description' => $content->description,
            'content' => $this->processContent($content->content, $options),
            'summary' => $this->generateSummary($content, $options['summary_length'] ?? 200),
            'reading_time' => $this->calculateReadingTime($content->content),
            'published_at' => $content->published_at?->toDateTimeString(),
            'views_count' => $content->views_count,
            'categories' => $content->categories->map(function ($category) {
                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'display_name' => $category->display_name,
                ];
            }),
            'meta' => $this->extractMetadata($content),
        ];

        // 如果需要，包含附件信息
        if ($options['include_attachments'] ?? false) {
            $data['attachments'] = $content->attachments->map(function ($attachment) {
                return [
                    'id' => $attachment->id,
                    'name' => $attachment->name,
                    'url' => $attachment->getUrl(),
                    'type' => $attachment->mime_type,
                    'size' => $attachment->getHumanReadableSize(),
                ];
            });
        }

        return $data;
    }

    /**
     * 内容验证规则
     *
     * @return array
     */
    public function validationRules(): array
    {
        return [
            'title' => 'required|string|max:200',
            'content' => 'required|string',
            'description' => 'nullable|string|max:500',
            'keywords' => 'nullable|string|max:1000',
            'published_at' => 'nullable|date',
        ];
    }

    /**
     * 处理内容
     *
     * @param string|null $content
     * @param array $options
     * @return string|null
     */
    protected function processContent(?string $content, array $options = []): ?string
    {
        if (!$content) {
            return null;
        }

        // 处理内容中的短代码或特殊标记
        $content = $this->processShortcodes($content);

        // 如果需要，清理HTML
        if ($options['clean_html'] ?? false) {
            $content = $this->cleanHtml($content);
        }

        return $content;
    }

    /**
     * 生成摘要
     *
     * @param Content $content
     * @param int $length
     * @return string
     */
    protected function generateSummary(Content $content, int $length = 200): string
    {
        // 如果有描述，优先使用描述
        if ($content->description) {
            return $content->description;
        }

        // 否则从内容中提取
        $text = strip_tags($content->content ?? '');
        return Str::limit($text, $length);
    }

    /**
     * 计算阅读时间（分钟）
     *
     * @param string|null $content
     * @param int $wordsPerMinute
     * @return int
     */
    protected function calculateReadingTime(?string $content, int $wordsPerMinute = 200): int
    {
        if (!$content) {
            return 0;
        }

        $text = strip_tags($content);
        $wordCount = str_word_count($text);
        
        // 中文字符计算
        $chineseCount = preg_match_all('/[\x{4e00}-\x{9fa5}]/u', $text, $matches);
        if ($chineseCount) {
            $wordCount += intval($chineseCount / 2); // 假设2个中文字符等于1个英文单词
        }

        return max(1, ceil($wordCount / $wordsPerMinute));
    }

    /**
     * 处理短代码
     *
     * @param string $content
     * @return string
     */
    protected function processShortcodes(string $content): string
    {
        // 示例：处理 [gallery id="123"] 这样的短代码
        $content = preg_replace_callback('/\[gallery\s+id="(\d+)"\]/', function ($matches) {
            // 这里可以替换为实际的图库HTML
            return sprintf('<div class="gallery" data-id="%s"></div>', $matches[1]);
        }, $content);

        return $content;
    }

    /**
     * 清理HTML
     *
     * @param string $content
     * @return string
     */
    protected function cleanHtml(string $content): string
    {
        // 允许的标签
        $allowedTags = '<p><br><strong><em><u><h1><h2><h3><h4><h5><h6><ul><ol><li><blockquote><a><img><pre><code>';
        
        return strip_tags($content, $allowedTags);
    }

    /**
     * 提取元数据
     *
     * @param Content $content
     * @return array
     */
    protected function extractMetadata(Content $content): array
    {
        $metadata = [];

        // 从内容元数据表中获取
        foreach ($content->metadata as $meta) {
            $metadata[$meta->key] = $meta->value;
        }

        // 添加计算的元数据
        $metadata['word_count'] = str_word_count(strip_tags($content->content ?? ''));
        $metadata['has_images'] = (bool) preg_match('/<img[^>]+>/i', $content->content ?? '');

        return $metadata;
    }

    /**
     * 获取支持的格式列表
     *
     * @return array
     */
    public function supportedFormats(): array
    {
        return ['default', 'summary', 'full', 'json', 'amp'];
    }

    /**
     * 摘要格式
     *
     * @param Content $content
     * @return array
     */
    protected function formatSummary(Content $content): array
    {
        return [
            'id' => $content->id,
            'title' => $content->title,
            'summary' => $this->generateSummary($content),
            'published_at' => $content->published_at?->toDateTimeString(),
            'reading_time' => $this->calculateReadingTime($content->content),
        ];
    }

    /**
     * 完整格式
     *
     * @param Content $content
     * @return array
     */
    protected function formatFull(Content $content): array
    {
        return $this->render($content, ['include_attachments' => true]);
    }

    /**
     * AMP格式
     *
     * @param Content $content
     * @return array
     */
    protected function formatAmp(Content $content): array
    {
        $data = $this->formatDefault($content);
        $data['content'] = $this->convertToAmp($content->content);
        return $data;
    }

    /**
     * 转换为AMP格式
     *
     * @param string|null $content
     * @return string|null
     */
    protected function convertToAmp(?string $content): ?string
    {
        if (!$content) {
            return null;
        }

        // 将img标签转换为amp-img
        $content = preg_replace(
            '/<img\s+([^>]*?)src="([^"]+)"([^>]*?)>/i',
            '<amp-img $1src="$2"$3 layout="responsive"></amp-img>',
            $content
        );

        return $content;
    }

    /**
     * 获取内容的元数据定义
     *
     * @return array
     */
    public function metadataDefinitions(): array
    {
        return [
            'author' => [
                'type' => 'string',
                'label' => '作者',
                'required' => false,
            ],
            'source' => [
                'type' => 'string',
                'label' => '来源',
                'required' => false,
            ],
            'tags' => [
                'type' => 'json',
                'label' => '标签',
                'required' => false,
            ],
            'seo_title' => [
                'type' => 'string',
                'label' => 'SEO标题',
                'required' => false,
            ],
            'seo_description' => [
                'type' => 'string',
                'label' => 'SEO描述',
                'required' => false,
            ],
        ];
    }
}