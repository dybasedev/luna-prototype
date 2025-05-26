<?php

namespace Dybasedev\LunaPrototype\Foundation\Configuration\Models;

use Dybasedev\LunaPrototype\Foundation\NamedId;
use Dybasedev\LunaPrototype\Foundation\VersionControlModel\VersionControl;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 *
 *
 * @property int $id
 * @property string $name
 * @property int $group_id 配置组ID
 * @property string $display_name 显示名
 * @property string $description 配置描述
 * @property string|null $current_version_id 当前版本ID，为空则无数据
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ConfigurationValue|null $current
 * @property-read Collection<int, ConfigurationValue> $versions
 * @property-read int|null $versions_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Configuration newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Configuration newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Configuration query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Configuration whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Configuration whereCurrentVersionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Configuration whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Configuration whereDisplayName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Configuration whereGroupId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Configuration whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Configuration whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Configuration whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Configuration whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class Configuration extends Model
{
    use VersionControl, NamedId;

    public $table = 'luna_configurations';

    public function versionValueModel(): string
    {
        return ConfigurationValue::class;
    }
}