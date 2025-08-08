<?php

namespace Dybasedev\LunaPrototype\Content\Models;

use Illuminate\Database\Eloquent\Model;
use Dybasedev\LunaPrototype\Content\LunaContentConfigure;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 内容元数据模型
 *
 * @property int $id
 * @property int $content_id 内容ID
 * @property string $key 元数据键
 * @property int $type 数据类型
 * @property string|null $string_value 字符串值
 * @property int|null $integer_value 整数值
 * @property string|null $float_value 浮点数值
 * @property bool|null $boolean_value 布尔值
 * @property array|null $json_value JSON值
 * @property \Illuminate\Support\Carbon|null $datetime_value 日期时间值
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read mixed $value 类型化的值
 * @property-read Content|null $content
 * @method static \Illuminate\Database\Eloquent\Builder|ContentMetadata newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|ContentMetadata newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|ContentMetadata query()
 * @method static \Illuminate\Database\Eloquent\Builder|ContentMetadata byKey(string|array $keys)
 * @method static \Illuminate\Database\Eloquent\Builder|ContentMetadata byType(int $type)
 * @method static \Illuminate\Database\Eloquent\Builder|ContentMetadata whereBooleanValue($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ContentMetadata whereContentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ContentMetadata whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ContentMetadata whereDatetimeValue($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ContentMetadata whereFloatValue($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ContentMetadata whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ContentMetadata whereIntegerValue($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ContentMetadata whereJsonValue($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ContentMetadata whereKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ContentMetadata whereStringValue($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ContentMetadata whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ContentMetadata whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class ContentMetadata extends Model
{
    /**
     * 表名
     *
     * @var string
     */
    protected $table = 'luna_content_metadata';

    /**
     * 可填充字段
     *
     * @var array
     */
    protected $fillable = [
        'content_id',
        'key',
        'type',
        'string_value',
        'integer_value',
        'float_value',
        'boolean_value',
        'json_value',
        'datetime_value',
    ];

    /**
     * 类型转换
     *
     * @var array
     */
    protected $casts = [
        'content_id' => 'integer',
        'type' => 'integer',
        'boolean_value' => 'boolean',
        'json_value' => 'array',
        'datetime_value' => 'datetime',
    ];

    /**
     * 支持的数据类型
     */
    const TYPE_STRING = 1;
    const TYPE_INTEGER = 2;
    const TYPE_FLOAT = 3;
    const TYPE_BOOLEAN = 4;
    const TYPE_JSON = 5;
    const TYPE_DATETIME = 6;

    /**
     * 获取内容
     *
     * @return BelongsTo
     */
    public function content(): BelongsTo
    {
        return $this->belongsTo(
            luna_module_configure(LunaContentConfigure::class)->contentModel,
            'content_id'
        );
    }

    /**
     * 获取类型化的值
     *
     * @return mixed
     */
    public function getTypedValue()
    {
        switch ($this->type) {
            case self::TYPE_INTEGER:
                return $this->integer_value;
            
            case self::TYPE_FLOAT:
                return (float) $this->float_value;
            
            case self::TYPE_BOOLEAN:
                return $this->boolean_value;
            
            case self::TYPE_JSON:
                return $this->json_value;
            
            case self::TYPE_DATETIME:
                return $this->datetime_value;
            
            case self::TYPE_STRING:
            default:
                return $this->string_value;
        }
    }

    /**
     * 设置类型化的值
     *
     * @param mixed $value
     * @return void
     */
    public function setTypedValue($value): void
    {
        // 重置所有值字段
        $this->string_value = null;
        $this->integer_value = null;
        $this->float_value = null;
        $this->boolean_value = null;
        $this->json_value = null;
        $this->datetime_value = null;

        if ($value instanceof \DateTimeInterface) {
            $this->type = self::TYPE_DATETIME;
            $this->datetime_value = $value;
        } elseif (is_bool($value)) {
            $this->type = self::TYPE_BOOLEAN;
            $this->boolean_value = $value;
        } elseif (is_int($value)) {
            $this->type = self::TYPE_INTEGER;
            $this->integer_value = $value;
        } elseif (is_float($value)) {
            $this->type = self::TYPE_FLOAT;
            $this->float_value = $value;
        } elseif (is_array($value) || is_object($value)) {
            $this->type = self::TYPE_JSON;
            $this->json_value = $value;
        } elseif (is_string($value) && $this->isDateTimeString($value)) {
            $this->type = self::TYPE_DATETIME;
            $this->datetime_value = \Illuminate\Support\Carbon::parse($value);
        } else {
            $this->type = self::TYPE_STRING;
            $this->string_value = (string) $value;
        }
    }

    /**
     * 创建元数据
     *
     * @param int $contentId
     * @param string $key
     * @param mixed $value
     * @param int|null $type
     * @return static
     */
    public static function createFor(int $contentId, string $key, $value, ?int $type = null): static
    {
        $metadata = new static([
            'content_id' => $contentId,
            'key' => $key,
        ]);

        if ($type !== null) {
            $metadata->type = $type;
            // 根据类型设置对应的值字段
            switch ($type) {
                case self::TYPE_INTEGER:
                    $metadata->integer_value = (int) $value;
                    break;
                case self::TYPE_FLOAT:
                    $metadata->float_value = (float) $value;
                    break;
                case self::TYPE_BOOLEAN:
                    $metadata->boolean_value = (bool) $value;
                    break;
                case self::TYPE_JSON:
                    $metadata->json_value = is_string($value) ? json_decode($value, true) : $value;
                    break;
                case self::TYPE_DATETIME:
                    $metadata->datetime_value = $value instanceof \DateTimeInterface ? $value : \Illuminate\Support\Carbon::parse($value);
                    break;
                case self::TYPE_STRING:
                default:
                    $metadata->string_value = (string) $value;
                    break;
            }
        } else {
            $metadata->setTypedValue($value);
        }

        $metadata->save();

        return $metadata;
    }

    /**
     * 批量设置元数据
     *
     * @param int $contentId
     * @param array $data
     * @return \Illuminate\Support\Collection
     */
    public static function batchSetFor(int $contentId, array $data)
    {
        $metadata = collect();

        foreach ($data as $key => $value) {
            $existing = static::where('content_id', $contentId)->where('key', $key)->first();
            
            if ($existing) {
                $existing->setTypedValue($value);
                $existing->save();
                $metadata->push($existing);
            } else {
                $item = static::createFor($contentId, $key, $value);
                $metadata->push($item);
            }
        }

        return $metadata;
    }

    /**
     * 检测值的类型
     *
     * @param mixed $value
     * @return int
     */
    protected static function detectType($value): int
    {
        if (is_null($value)) {
            return self::TYPE_JSON; // null 值默认作为 JSON 存储
        } elseif ($value instanceof \DateTimeInterface) {
            return self::TYPE_DATETIME;
        } elseif (is_bool($value)) {
            return self::TYPE_BOOLEAN;
        } elseif (is_int($value)) {
            return self::TYPE_INTEGER;
        } elseif (is_float($value)) {
            return self::TYPE_FLOAT;
        } elseif (is_array($value) || is_object($value)) {
            return self::TYPE_JSON;
        } elseif (is_string($value) && (new static)->isDateTimeString($value)) {
            return self::TYPE_DATETIME;
        } else {
            return self::TYPE_STRING;
        }
    }

    /**
     * 检查字符串是否为日期时间格式
     *
     * @param string $value
     * @return bool
     */
    protected function isDateTimeString(string $value): bool
    {
        // 常见的日期时间格式
        $patterns = [
            '/^\d{4}-\d{2}-\d{2}$/', // Y-m-d
            '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', // Y-m-d H:i:s
            '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', // Y-m-d H:i
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', // ISO 8601
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $value)) {
                try {
                    \Illuminate\Support\Carbon::parse($value);
                    return true;
                } catch (\Exception $e) {
                    return false;
                }
            }
        }

        return false;
    }

    /**
     * 按键查询作用域
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string|array $keys
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByKey($query, $keys)
    {
        if (is_array($keys)) {
            return $query->whereIn('key', $keys);
        }

        return $query->where('key', $keys);
    }

    /**
     * 按类型查询作用域
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $type
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByType($query, int $type)
    {
        return $query->where('type', $type);
    }

    /**
     * 获取值的访问器（动态获取正确的值字段）
     *
     * @return mixed
     */
    public function getValueAttribute()
    {
        return $this->getTypedValue();
    }

    /**
     * 设置值的修改器（动态设置正确的值字段）
     *
     * @param mixed $value
     * @return void
     */
    public function setValueAttribute($value)
    {
        $this->setTypedValue($value);
    }
}