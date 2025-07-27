<?php

namespace Dybasedev\LunaPrototype\Showcase\Helpers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * 查询辅助工具类
 * 
 * 提供常用的查询条件构建方法，配合 Laravel Builder 的 when() 方法使用
 * 
 * @example
 * $query->when(...QueryHelper::dateBetween($request, 'created_at'));
 * $query->when(...QueryHelper::applyCondition($request, 'status'));
 */
class QueryHelper
{
    /**
     * 日期范围条件
     * 
     * @param Request $request
     * @param string $field
     * @param string|null $inputKey
     * @return array 返回 [condition, callback] 用于 when() 方法
     */
    public static function dateBetween(Request $request, string $field, ?string $inputKey = null): array
    {
        $inputKey = $inputKey ?? $field;
        $value = $request->input($inputKey);
        
        if (empty($value) || !is_array($value) || count($value) !== 2) {
            return [false, null];
        }
        
        return [
            true,
            fn($query) => $query->whereBetween($field, [$value[0], $value[1]])
        ];
    }
    
    /**
     * 排序条件
     * 
     * @param Request $request
     * @param array $allowedFields
     * @param string $sortFieldKey
     * @param string $sortOrderKey
     * @return array 返回 [condition, callback] 用于 when() 方法
     */
    public static function applySorter(
        Request $request, 
        array $allowedFields = [], 
        string $sortFieldKey = 'sorter.field',
        string $sortOrderKey = 'sorter.order'
    ): array {
        $field = $request->input($sortFieldKey);
        $order = $request->input($sortOrderKey);
        
        if (empty($field) || !in_array($order, ['ascend', 'descend'])) {
            return [false, null];
        }
        
        if (!empty($allowedFields) && !in_array($field, $allowedFields)) {
            return [false, null];
        }
        
        $direction = $order === 'ascend' ? 'asc' : 'desc';
        
        return [
            true,
            fn($query) => $query->orderBy($field, $direction)
        ];
    }
    
    /**
     * 通用条件过滤
     * 
     * @param Request $request
     * @param string $field
     * @param string|null $inputKey
     * @param string $operator
     * @return array 返回 [condition, callback] 用于 when() 方法
     */
    public static function applyCondition(
        Request $request, 
        string $field, 
        ?string $inputKey = null,
        string $operator = '='
    ): array {
        $inputKey = $inputKey ?? $field;
        $value = $request->input($inputKey);
        
        if (is_null($value) || $value === '') {
            return [false, null];
        }
        
        return [
            true,
            fn($query) => $query->where($field, $operator, $value)
        ];
    }
    
    /**
     * 模糊搜索条件
     * 
     * @param Request $request
     * @param array $searchFields
     * @param string $inputKey
     * @return array 返回 [condition, callback] 用于 when() 方法
     */
    public static function searchLike(
        Request $request, 
        array $searchFields, 
        string $inputKey = 'search'
    ): array {
        $searchValue = $request->input($inputKey);
        
        if (empty($searchValue) || empty($searchFields)) {
            return [false, null];
        }
        
        return [
            true,
            function($query) use ($searchFields, $searchValue) {
                $query->where(function($q) use ($searchFields, $searchValue) {
                    foreach ($searchFields as $field) {
                        $q->orWhere($field, 'like', "%{$searchValue}%");
                    }
                });
            }
        ];
    }
    
    /**
     * 数值范围过滤
     * 
     * @param Request $request
     * @param string $field
     * @param string|null $minKey
     * @param string|null $maxKey
     * @return array 返回 [condition, callback] 用于 when() 方法
     */
    public static function numberRange(
        Request $request, 
        string $field,
        ?string $minKey = null,
        ?string $maxKey = null
    ): array {
        $minKey = $minKey ?? $field . '_min';
        $maxKey = $maxKey ?? $field . '_max';
        
        $min = $request->input($minKey);
        $max = $request->input($maxKey);
        
        if (is_null($min) && is_null($max)) {
            return [false, null];
        }
        
        return [
            true,
            function($query) use ($field, $min, $max) {
                if (!is_null($min)) {
                    $query->where($field, '>=', $min);
                }
                if (!is_null($max)) {
                    $query->where($field, '<=', $max);
                }
            }
        ];
    }
    
    /**
     * IN 条件过滤
     * 
     * @param Request $request
     * @param string $field
     * @param string|null $inputKey
     * @return array 返回 [condition, callback] 用于 when() 方法
     */
    public static function whereIn(
        Request $request, 
        string $field,
        ?string $inputKey = null
    ): array {
        $inputKey = $inputKey ?? $field;
        $value = $request->input($inputKey);
        
        if (empty($value)) {
            return [false, null];
        }
        
        // 支持逗号分隔的字符串或数组
        if (is_string($value)) {
            $value = array_filter(explode(',', $value));
        }
        
        if (!is_array($value) || empty($value)) {
            return [false, null];
        }
        
        return [
            true,
            fn($query) => $query->whereIn($field, $value)
        ];
    }
    
    /**
     * 布尔值过滤
     * 
     * @param Request $request
     * @param string $field
     * @param string|null $inputKey
     * @return array 返回 [condition, callback] 用于 when() 方法
     */
    public static function booleanValue(
        Request $request, 
        string $field,
        ?string $inputKey = null
    ): array {
        $inputKey = $inputKey ?? $field;
        $value = $request->input($inputKey);
        
        if (is_null($value) || $value === '') {
            return [false, null];
        }
        
        $boolValue = null;
        
        // 处理各种布尔值表示
        if (is_bool($value)) {
            $boolValue = $value;
        } elseif (is_string($value)) {
            $value = strtolower($value);
            if (in_array($value, ['true', '1', 'yes', 'on'])) {
                $boolValue = true;
            } elseif (in_array($value, ['false', '0', 'no', 'off'])) {
                $boolValue = false;
            }
        } elseif (is_numeric($value)) {
            $boolValue = (bool) $value;
        }
        
        if (is_null($boolValue)) {
            return [false, null];
        }
        
        return [
            true,
            fn($query) => $query->where($field, $boolValue)
        ];
    }
    
    /**
     * 存在性条件
     * 
     * @param Request $request
     * @param string $relation
     * @param string|null $inputKey
     * @return array 返回 [condition, callback] 用于 when() 方法
     */
    public static function hasRelation(
        Request $request,
        string $relation,
        ?string $inputKey = null
    ): array {
        $inputKey = $inputKey ?? 'has_' . $relation;
        $value = $request->input($inputKey);
        
        if (empty($value)) {
            return [false, null];
        }
        
        return [
            true,
            fn($query) => $query->has($relation)
        ];
    }
    
    /**
     * 不存在性条件
     * 
     * @param Request $request
     * @param string $relation
     * @param string|null $inputKey
     * @return array 返回 [condition, callback] 用于 when() 方法
     */
    public static function doesntHaveRelation(
        Request $request,
        string $relation,
        ?string $inputKey = null
    ): array {
        $inputKey = $inputKey ?? 'no_' . $relation;
        $value = $request->input($inputKey);
        
        if (empty($value)) {
            return [false, null];
        }
        
        return [
            true,
            fn($query) => $query->doesntHave($relation)
        ];
    }
}