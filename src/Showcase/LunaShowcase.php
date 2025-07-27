<?php

namespace Dybasedev\LunaPrototype\Showcase;

use Dybasedev\LunaPrototype\Foundation\LunaModule;
use Dybasedev\LunaPrototype\Showcase\DataTable\DataTableManager;
use Dybasedev\LunaPrototype\Showcase\RemoteSchema\RemoteSchemaManager;

/**
 * Showcase 组件主类
 * 
 * 提供 UI 组件抽象层功能，包括 DataTable、表单等组件的管理
 */
class LunaShowcase extends LunaModule
{
    /**
     * DataTable 管理器
     * 
     * @var DataTableManager|null
     */
    protected ?DataTableManager $dataTableManager = null;

    /**
     * RemoteSchema 管理器
     * 
     * @var RemoteSchemaManager|null
     */
    protected ?RemoteSchemaManager $remoteSchemaManager = null;

    /**
     * 构造函数
     * 
     * @param LunaShowcaseConfigure $configure
     */
    public function __construct(
        protected LunaShowcaseConfigure $configure
    ) {
    }

    /**
     * 获取 DataTable 管理器
     * 
     * @return DataTableManager
     */
    public function dataTable(): DataTableManager
    {
        if (is_null($this->dataTableManager)) {
            $this->dataTableManager = new DataTableManager(
                $this->configure->getDataTableRegistry(),
                $this->configure->getAdapter()
            );
        }
        
        return $this->dataTableManager;
    }

    /**
     * 获取 RemoteSchema 管理器
     * 
     * @return RemoteSchemaManager
     */
    public function remoteSchema(): RemoteSchemaManager
    {
        if (is_null($this->remoteSchemaManager)) {
            $this->remoteSchemaManager = new RemoteSchemaManager(
                $this->configure->getRemoteSchemaRegistry(),
                $this->configure->getAdapter()
            );
        }
        
        return $this->remoteSchemaManager;
    }

    /**
     * 获取适配器
     * 
     * @param string|null $name
     * @return Adapter
     */
    public function adapter(?string $name = null): Adapter
    {
        return $this->configure->getAdapter($name);
    }
}