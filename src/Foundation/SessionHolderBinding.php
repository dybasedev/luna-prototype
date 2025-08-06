<?php

namespace Dybasedev\LunaPrototype\Foundation;

use Dybasedev\LunaPrototype\Foundation\Exception\LunaException;
use Illuminate\Database\Eloquent\Model;

/**
 * 会话持有者绑定对象
 *
 * 在各个 Luna 模块中，可能依赖会话对应的用户、会员进行一些关联业务流程，
 * 可以通过这个绑定对象进并注册访问
 */
class SessionHolderBinding
{
    /**
     * @var class-string<Model>|string|null 表名或模型名称
     */
    protected(set) ?string $table = null;

    /**
     * @var bool 表是否为模型类
     */
    protected(set) bool $tableIsModelClass = false;

    protected(set) ?string $tableName = null {
        get {
            return $this->tableName ??= $this->tableIsModelClass ? (new $this->table)->getTable() : $this->table;
        }
    }

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

    /**
     * 设置绑定的表或模型
     *
     * @param string $table
     * @return $this
     */
    public function table(string $table): static
    {
        $this->table = $table;

        // 检查是否是模型类
        if (class_exists($table)) {
            try {
                if ((new $table) instanceof Model) {
                    $this->tableIsModelClass = true;
                }
            } catch (\Throwable $e) {
                // 不是模型类，保持 tableIsModelClass 为 false
            }
        }

        return $this;
    }

    public function keyName(string $name): static
    {
        $this->keyName = $name;
        return $this;
    }

    /**
     * 获取目标类（绑定的模型类）
     *
     * @return string|class-string<Model>|null
     */
    public function getTargetClass(): ?string
    {
        if (!$this->table || !$this->tableIsModelClass) {
            return null;
        }
        
        return $this->table;
    }
}