<?php

namespace Dybasedev\LunaPrototype\Content\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ContentVersion extends Model
{
    /**
     * 表名
     *
     * @var string
     */
    protected $table = 'luna_content_versions';

    /**
     * 主键
     *
     * @var string
     */
    protected $primaryKey = 'version_id';

    /**
     * 主键类型
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * 不自增主键
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * 可填充字段
     *
     * @var array
     */
    protected $fillable = [
        'version_id',
        'content_id',
        'content',
        'payload',
        'version_name',
        'version_note',
        'editor_type',
        'editor_id',
    ];

    /**
     * 类型转换
     *
     * @var array
     */
    protected $casts = [
        'content_id' => 'integer',
        'payload' => 'array',
        'editor_type' => 'integer',
        'editor_id' => 'integer',
    ];

    /**
     * 默认属性值
     *
     * @var array
     */
    protected $attributes = [
        'payload' => '[]',
    ];

    /**
     * 获取内容
     *
     * @return BelongsTo
     */
    public function content(): BelongsTo
    {
        return $this->belongsTo(Content::class, 'content_id');
    }

    /**
     * 获取编辑者
     *
     * @return MorphTo
     */
    public function editor(): MorphTo
    {
        return $this->morphTo('editor', 'editor_type', 'editor_id');
    }

    /**
     * 判断是否为当前版本
     *
     * @return bool
     */
    public function isCurrent(): bool
    {
        return $this->content && $this->content->current_version_id === $this->version_id;
    }

    /**
     * 应用此版本为当前版本
     *
     * @return bool
     */
    public function apply(): bool
    {
        if (!$this->content) {
            return false;
        }

        return $this->content->update(['current_version_id' => $this->version_id]);
    }

    /**
     * 获取版本的显示名称
     *
     * @return string
     */
    public function getDisplayNameAttribute(): string
    {
        if ($this->version_name) {
            return $this->version_name;
        }

        return sprintf('版本 %s', $this->created_at->format('Y-m-d H:i:s'));
    }

    /**
     * 获取版本信息摘要
     *
     * @return array
     */
    public function getSummary(): array
    {
        return [
            'version_id' => $this->version_id,
            'version_name' => $this->version_name,
            'version_note' => $this->version_note,
            'is_current' => $this->isCurrent(),
            'editor' => $this->editor ? [
                'type' => get_class($this->editor),
                'id' => $this->editor->getOperatorId(),
                'name' => method_exists($this->editor, 'getName') ? $this->editor->getName() : null,
            ] : null,
            'created_at' => $this->created_at->toDateTimeString(),
        ];
    }
}