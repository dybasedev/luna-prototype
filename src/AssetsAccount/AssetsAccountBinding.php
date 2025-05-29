<?php

namespace Dybasedev\LunaPrototype\AssetsAccount;

use Dybasedev\LunaPrototype\Foundation\Exception\LunaException;
use Dybasedev\LunaPrototype\Foundation\SessionHolder;
use Illuminate\Database\Eloquent\Model;

/**
 * 资产账户绑定对象
 */
class AssetsAccountBinding
{
    /**
     * @var class-string<Model>|null 表名或模型名称
     */
    protected(set) ?string $table = null;

    /**
     * @var bool 表是否为模型类
     */
    protected(set) bool $tableIsModelClass = false;

    /**
     * @var string
     */
    protected(set) string $keyName = 'id';

    /**
     * @param class-string<SessionHolder> $owner
     */
    public function __construct(protected(set) string $owner)
    {
        if (!((new $this->owner) instanceof SessionHolder)) {
            throw LunaException::create('不支持的绑定对象类型');
        }
    }

    public function table(string $table): static
    {
        $this->table = $table;

        if ((new $table) instanceof Model) {
            $this->tableIsModelClass = true;
        }

        return $this;
    }

    public function keyName(string $name): static
    {
        $this->keyName = $name;
        return $this;
    }
}