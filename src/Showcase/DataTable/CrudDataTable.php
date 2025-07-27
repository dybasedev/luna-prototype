<?php

namespace Dybasedev\LunaPrototype\Showcase\DataTable;

use Dybasedev\LunaPrototype\Foundation\Exception\LunaException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * 支持 CRUD 操作的数据表格抽象类
 * 
 * 继承此类可以快速实现具有增删改查功能的数据表格
 */
abstract class CrudDataTable extends DataTable
{
    /**
     * 获取模型类名
     * 
     * @return class-string<Model>
     */
    abstract protected function model(): string;

    /**
     * 获取创建验证规则
     * 
     * @param Request $request
     * @return array
     */
    protected function createRules(Request $request): array
    {
        return [];
    }

    /**
     * 获取更新验证规则
     * 
     * @param Request $request
     * @param Model $model
     * @return array
     */
    protected function updateRules(Request $request, Model $model): array
    {
        return $this->createRules($request);
    }

    /**
     * 创建记录
     * 
     * @param Request $request
     * @return mixed
     * @throws LunaException
     */
    public function create(Request $request): mixed
    {
        $this->validateCreate($request);
        
        $data = $this->prepareCreateData($request);
        
        try {
            $model = DB::transaction(function () use ($data, $request) {
                /** @var Model $model */
                $model = new ($this->model())($data);
                
                $this->beforeCreate($model, $request);
                
                $model->save();
                
                $this->afterCreate($model, $request);
                
                return $model;
            });
            
            return $this->mapRecord($model, $request);
        } catch (\Exception $e) {
            throw LunaException::create('Failed to create record: ' . $e->getMessage())
                ->withDisplayMessage('创建记录失败')
                ->withData(['error' => $e->getMessage()]);
        }
    }

    /**
     * 更新记录
     * 
     * @param Request $request
     * @return mixed
     * @throws LunaException
     */
    public function update(Request $request): mixed
    {
        $id = $request->input('id');
        
        if (!$id) {
            throw LunaException::create('Missing record ID')
                ->withDisplayMessage('缺少记录ID');
        }
        
        /** @var Model|null $model */
        $model = $this->query($request)->find($id);
        
        if (!$model) {
            throw LunaException::create('Record not found')
                ->withDisplayMessage('记录不存在');
        }
        
        $this->validateUpdate($request, $model);
        
        $data = $this->prepareUpdateData($request, $model);
        
        try {
            DB::transaction(function () use ($model, $data, $request) {
                $this->beforeUpdate($model, $request);
                
                $model->fill($data);
                $model->save();
                
                $this->afterUpdate($model, $request);
            });
            
            return $this->mapRecord($model->fresh(), $request);
        } catch (\Exception $e) {
            throw LunaException::create('Failed to update record: ' . $e->getMessage())
                ->withDisplayMessage('更新记录失败')
                ->withData(['error' => $e->getMessage()]);
        }
    }

    /**
     * 删除记录
     * 
     * @param Request $request
     * @return mixed
     * @throws LunaException
     */
    public function delete(Request $request): mixed
    {
        $id = $request->input('id');
        
        if (!$id) {
            throw LunaException::create('Missing record ID')
                ->withDisplayMessage('缺少记录ID');
        }
        
        /** @var Model|null $model */
        $model = $this->query($request)->find($id);
        
        if (!$model) {
            throw LunaException::create('Record not found')
                ->withDisplayMessage('记录不存在');
        }
        
        try {
            DB::transaction(function () use ($model, $request) {
                $this->beforeDelete($model, $request);
                
                $model->delete();
                
                $this->afterDelete($model, $request);
            });
            
            return true;
        } catch (\Exception $e) {
            throw LunaException::create('Failed to delete record: ' . $e->getMessage())
                ->withDisplayMessage('删除记录失败')
                ->withData(['error' => $e->getMessage()]);
        }
    }

    /**
     * 批量删除记录
     * 
     * @param Request $request
     * @return int
     * @throws LunaException
     */
    public function batchDelete(Request $request): int
    {
        $ids = $request->input('ids', []);
        
        if (empty($ids)) {
            throw LunaException::create('No records selected')
                ->withDisplayMessage('未选择任何记录');
        }
        
        try {
            $count = 0;
            
            DB::transaction(function () use ($ids, $request, &$count) {
                $models = $this->query($request)->whereIn('id', $ids)->get();
                
                foreach ($models as $model) {
                    $this->beforeDelete($model, $request);
                    $model->delete();
                    $this->afterDelete($model, $request);
                    $count++;
                }
            });
            
            return $count;
        } catch (\Exception $e) {
            throw LunaException::create('Failed to batch delete records: ' . $e->getMessage())
                ->withDisplayMessage('批量删除失败')
                ->withData(['error' => $e->getMessage()]);
        }
    }

    /**
     * 验证创建数据
     * 
     * @param Request $request
     * @throws LunaException
     */
    protected function validateCreate(Request $request): void
    {
        $rules = $this->createRules($request);
        
        if (!empty($rules)) {
            $validator = Validator::make($request->all(), $rules);
            
            if ($validator->fails()) {
                throw LunaException::create('Validation failed')
                    ->withDisplayMessage('数据验证失败')
                    ->withData(['errors' => $validator->errors()->toArray()]);
            }
        }
    }

    /**
     * 验证更新数据
     * 
     * @param Request $request
     * @param Model $model
     * @throws LunaException
     */
    protected function validateUpdate(Request $request, Model $model): void
    {
        $rules = $this->updateRules($request, $model);
        
        if (!empty($rules)) {
            $validator = Validator::make($request->all(), $rules);
            
            if ($validator->fails()) {
                throw LunaException::create('Validation failed')
                    ->withDisplayMessage('数据验证失败')
                    ->withData(['errors' => $validator->errors()->toArray()]);
            }
        }
    }

    /**
     * 准备创建数据
     * 
     * @param Request $request
     * @return array
     */
    protected function prepareCreateData(Request $request): array
    {
        return $request->all();
    }

    /**
     * 准备更新数据
     * 
     * @param Request $request
     * @param Model $model
     * @return array
     */
    protected function prepareUpdateData(Request $request, Model $model): array
    {
        return $request->all();
    }

    /**
     * 创建前钩子
     * 
     * @param Model $model
     * @param Request $request
     * @return void
     */
    protected function beforeCreate(Model $model, Request $request): void
    {
        // 子类可以重写此方法
    }

    /**
     * 创建后钩子
     * 
     * @param Model $model
     * @param Request $request
     * @return void
     */
    protected function afterCreate(Model $model, Request $request): void
    {
        // 子类可以重写此方法
    }

    /**
     * 更新前钩子
     * 
     * @param Model $model
     * @param Request $request
     * @return void
     */
    protected function beforeUpdate(Model $model, Request $request): void
    {
        // 子类可以重写此方法
    }

    /**
     * 更新后钩子
     * 
     * @param Model $model
     * @param Request $request
     * @return void
     */
    protected function afterUpdate(Model $model, Request $request): void
    {
        // 子类可以重写此方法
    }

    /**
     * 删除前钩子
     * 
     * @param Model $model
     * @param Request $request
     * @return void
     */
    protected function beforeDelete(Model $model, Request $request): void
    {
        // 子类可以重写此方法
    }

    /**
     * 删除后钩子
     * 
     * @param Model $model
     * @param Request $request
     * @return void
     */
    protected function afterDelete(Model $model, Request $request): void
    {
        // 子类可以重写此方法
    }

    /**
     * 获取权限配置
     * 
     * @param Request $request
     * @return array
     */
    protected function getPermissions(Request $request): array
    {
        return [
            'create' => true,
            'update' => true,
            'delete' => true,
            'export' => false,
        ];
    }
}