<?php

namespace Dybasedev\LunaPrototype\Foundation\Configuration\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 *
 *
 * @property string $version_id SHA1 Hash
 * @property int $index_id 配置ID
 * @property array<array-key, mixed> $value 配置值
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Configuration|null $index
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ConfigurationValue newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ConfigurationValue newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ConfigurationValue query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ConfigurationValue whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ConfigurationValue whereIndexId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ConfigurationValue whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ConfigurationValue whereValue($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ConfigurationValue whereVersionId($value)
 * @mixin \Eloquent
 */
class ConfigurationValue extends Model
{
    public $table = 'luna_configuration_values';

    public $incrementing = false;

    public function indexModel(): string
    {
        return Configuration::class;
    }

    public function index(): BelongsTo
    {
        return $this->belongsTo($this->indexModel(), 'configuration_id', 'id');
    }

    protected function casts(): array
    {
        return [
            'value' => 'array',
        ];
    }
}