<?php

namespace Dybasedev\LunaPrototype\Content\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * 内容附件模型
 *
 * @property int $id
 * @property int|null $owner_type 所有者类型
 * @property int|null $owner_id 所有者ID
 * @property string $name 文件名称
 * @property string $original_name 原始文件名
 * @property string $path 文件路径或URL
 * @property string $disk 存储磁盘
 * @property string|null $mime_type MIME类型
 * @property int $size 文件大小（字节）
 * @property array|null $metadata 元数据
 * @property string|null $hash 文件哈希值
 * @property int $downloads 下载次数
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Model|\Eloquent|null $owner
 * @method static \Illuminate\Database\Eloquent\Builder|ContentAttachment newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|ContentAttachment newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|ContentAttachment query()
 * @method static \Illuminate\Database\Eloquent\Builder|ContentAttachment byType(string $type)
 * @method static \Illuminate\Database\Eloquent\Builder|ContentAttachment byDisk(string $disk)
 * @method static \Illuminate\Database\Eloquent\Builder|ContentAttachment whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ContentAttachment whereDisk($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ContentAttachment whereDownloads($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ContentAttachment whereHash($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ContentAttachment whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ContentAttachment whereMetadata($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ContentAttachment whereMimeType($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ContentAttachment whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ContentAttachment whereOriginalName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ContentAttachment whereOwnerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ContentAttachment whereOwnerType($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ContentAttachment wherePath($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ContentAttachment whereSize($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ContentAttachment whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class ContentAttachment extends Model
{
    /**
     * 表名
     *
     * @var string
     */
    protected $table = 'luna_content_attachments';

    /**
     * 可填充字段
     *
     * @var array
     */
    protected $fillable = [
        'owner_type',
        'owner_id',
        'name',
        'original_name',
        'path',
        'disk',
        'mime_type',
        'size',
        'metadata',
        'hash',
        'downloads',
    ];

    /**
     * 类型转换
     *
     * @var array
     */
    protected $casts = [
        'owner_type' => 'integer',
        'owner_id' => 'integer',
        'size' => 'integer',
        'metadata' => 'array',
        'downloads' => 'integer',
    ];

    /**
     * 获取所有者
     *
     * @return MorphTo
     */
    public function owner(): MorphTo
    {
        return $this->morphTo('owner', 'owner_type', 'owner_id');
    }

    /**
     * 获取存储实例
     *
     * @return \Illuminate\Contracts\Filesystem\Filesystem
     */
    public function storage()
    {
        return Storage::disk($this->disk);
    }

    /**
     * 获取文件URL
     *
     * @return string|null
     */
    public function getUrl(): ?string
    {
        if (Str::startsWith($this->path, ['http://', 'https://'])) {
            return $this->path;
        }

        try {
            return $this->storage()->url($this->path);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * 获取临时URL
     *
     * @param \DateTimeInterface $expiration
     * @param array $options
     * @return string|null
     */
    public function getTemporaryUrl(\DateTimeInterface $expiration, array $options = []): ?string
    {
        if (Str::startsWith($this->path, ['http://', 'https://'])) {
            return $this->path;
        }

        try {
            return $this->storage()->temporaryUrl($this->path, $expiration, $options);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * 获取文件内容
     *
     * @return string|null
     */
    public function getContents(): ?string
    {
        if (Str::startsWith($this->path, ['http://', 'https://'])) {
            return file_get_contents($this->path);
        }

        try {
            return $this->storage()->get($this->path);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * 下载文件
     *
     * @param string|null $name
     * @param array $headers
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function download(?string $name = null, array $headers = [])
    {
        $this->increment('downloads');

        $name = $name ?: $this->original_name;

        if (Str::startsWith($this->path, ['http://', 'https://'])) {
            return redirect($this->path);
        }

        return $this->storage()->download($this->path, $name, $headers);
    }

    /**
     * 删除文件
     *
     * @param bool $deleteRecord
     * @return bool
     */
    public function deleteFile(bool $deleteRecord = true): bool
    {
        try {
            if (!Str::startsWith($this->path, ['http://', 'https://']) && $this->storage()->exists($this->path)) {
                $this->storage()->delete($this->path);
            }

            if ($deleteRecord) {
                return $this->delete();
            }

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 检查文件是否存在
     *
     * @return bool
     */
    public function exists(): bool
    {
        if (Str::startsWith($this->path, ['http://', 'https://'])) {
            return true; // 假设远程文件存在
        }

        try {
            return $this->storage()->exists($this->path);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 获取文件扩展名
     *
     * @return string
     */
    public function getExtension(): string
    {
        return pathinfo($this->original_name, PATHINFO_EXTENSION);
    }

    /**
     * 获取人类可读的文件大小
     *
     * @param int $precision
     * @return string
     */
    public function getHumanReadableSize(int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $size = $this->size;

        for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
            $size /= 1024;
        }

        return round($size, $precision) . ' ' . $units[$i];
    }

    /**
     * 判断是否为图片
     *
     * @return bool
     */
    public function isImage(): bool
    {
        return Str::startsWith($this->mime_type, 'image/');
    }

    /**
     * 判断是否为视频
     *
     * @return bool
     */
    public function isVideo(): bool
    {
        return Str::startsWith($this->mime_type, 'video/');
    }

    /**
     * 判断是否为音频
     *
     * @return bool
     */
    public function isAudio(): bool
    {
        return Str::startsWith($this->mime_type, 'audio/');
    }

    /**
     * 判断是否为文档
     *
     * @return bool
     */
    public function isDocument(): bool
    {
        $documentMimeTypes = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'text/plain',
        ];

        return in_array($this->mime_type, $documentMimeTypes);
    }

    /**
     * 创建附件从上传的文件
     *
     * @param \Illuminate\Http\UploadedFile $file
     * @param array $attributes
     * @param string $disk
     * @param string $directory
     * @return static
     */
    public static function createFromUploadedFile($file, array $attributes = [], string $disk = 'public', string $directory = 'attachments')
    {
        $name = $attributes['name'] ?? $file->getClientOriginalName();
        $originalName = $file->getClientOriginalName();
        $path = $file->store($directory, $disk);
        $hash = hash_file('sha256', $file->getRealPath());

        return static::create(array_merge($attributes, [
            'name' => $name,
            'original_name' => $originalName,
            'path' => $path,
            'disk' => $disk,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'hash' => $hash,
        ]));
    }

    /**
     * 创建附件从URL
     *
     * @param string $url
     * @param array $attributes
     * @return static
     */
    public static function createFromUrl(string $url, array $attributes = [])
    {
        $name = $attributes['name'] ?? basename(parse_url($url, PHP_URL_PATH)) ?: 'remote-file';
        $originalName = $attributes['original_name'] ?? $name;

        // 获取文件信息
        $headers = @get_headers($url, 1);
        $size = isset($headers['Content-Length']) ? (int) $headers['Content-Length'] : 0;
        $mimeType = $headers['Content-Type'] ?? 'application/octet-stream';

        if (is_array($mimeType)) {
            $mimeType = end($mimeType);
        }

        return static::create(array_merge($attributes, [
            'name' => $name,
            'original_name' => $originalName,
            'path' => $url,
            'disk' => 'remote',
            'mime_type' => $mimeType,
            'size' => $size,
        ]));
    }

    /**
     * 按类型查询作用域
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $type
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByType($query, string $type)
    {
        switch ($type) {
            case 'image':
                return $query->where('mime_type', 'like', 'image/%');
            case 'video':
                return $query->where('mime_type', 'like', 'video/%');
            case 'audio':
                return $query->where('mime_type', 'like', 'audio/%');
            case 'document':
                $documentMimeTypes = [
                    'application/pdf',
                    'application/msword',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'application/vnd.ms-excel',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'application/vnd.ms-powerpoint',
                    'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                    'text/plain',
                ];
                return $query->whereIn('mime_type', $documentMimeTypes);
            default:
                return $query;
        }
    }

    /**
     * 按磁盘查询作用域
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $disk
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByDisk($query, string $disk)
    {
        return $query->where('disk', $disk);
    }
}