<?php

namespace Dybasedev\LunaPrototype\Content\Models;

use Illuminate\Database\Eloquent\Model;
use Dybasedev\LunaPrototype\Content\LunaContentConfigure;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContentCategory extends Model
{
    /**
     * 表名
     *
     * @var string
     */
    protected $table = 'luna_content_categories';

    /**
     * 可填充字段
     *
     * @var array
     */
    protected $fillable = [
        'parent_id',
        'name',
        'display_name',
        'description',
        'icon',
        'payload',
        'sort',
        'is_active',
    ];

    /**
     * 类型转换
     *
     * @var array
     */
    protected $casts = [
        'parent_id' => 'integer',
        'payload' => 'array',
        'sort' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * 默认属性值
     *
     * @var array
     */
    protected $attributes = [
        'parent_id' => 0,
        'sort' => 0,
        'is_active' => true,
        'description' => '',
    ];

    /**
     * 获取父分类
     *
     * @return BelongsTo
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(
            luna_module_configure(LunaContentConfigure::class)->categoryModel,
            'parent_id'
        );
    }

    /**
     * 获取子分类
     *
     * @return HasMany
     */
    public function children(): HasMany
    {
        return $this->hasMany(
            luna_module_configure(LunaContentConfigure::class)->categoryModel,
            'parent_id'
        )
            ->orderBy('sort')
            ->orderBy('id');
    }

    /**
     * 获取所有子孙分类
     *
     * @return HasMany
     */
    public function descendants(): HasMany
    {
        return $this->children()->with('descendants');
    }

    /**
     * 获取内容
     *
     * @return BelongsToMany
     */
    public function contents(): BelongsToMany
    {
        return $this->belongsToMany(
            luna_module_configure(LunaContentConfigure::class)->contentModel,
            'luna_content_category_relations',
            'category_id',
            'content_id'
        )
            ->withPivot('sort')
            ->withTimestamps()
            ->orderBy('pivot_sort');
    }

    /**
     * 获取已发布的内容
     *
     * @return BelongsToMany
     */
    public function publishedContents(): BelongsToMany
    {
        return $this->contents()->published();
    }

    /**
     * 获取分类路径
     *
     * @param string $separator
     * @param string $field
     * @return string
     */
    public function getPath(string $separator = '/', string $field = 'name'): string
    {
        $path = [];
        $category = $this;
        
        while ($category) {
            array_unshift($path, $category->{$field});
            $category = $category->parent;
        }
        
        return implode($separator, $path);
    }

    /**
     * 获取分类路径名称
     *
     * @param string $separator
     * @return string
     */
    public function getPathName(string $separator = ' / '): string
    {
        return $this->getPath($separator, 'display_name');
    }

    /**
     * 判断是否为指定分类的子孙
     *
     * @param int|ContentCategory $category
     * @return bool
     */
    public function isDescendantOf($category): bool
    {
        $categoryId = $category instanceof static ? $category->id : $category;
        
        $parent = $this->parent;
        while ($parent) {
            if ($parent->id === $categoryId) {
                return true;
            }
            $parent = $parent->parent;
        }

        return false;
    }

    /**
     * 判断是否有子分类
     *
     * @return bool
     */
    public function hasChildren(): bool
    {
        return $this->children()->exists();
    }

    /**
     * 获取层级深度
     *
     * @return int
     */
    public function getDepth(): int
    {
        $depth = 0;
        $parent = $this->parent;

        while ($parent) {
            $depth++;
            $parent = $parent->parent;
        }

        return $depth;
    }


    /**
     * 获取面包屑（分类对象列表）
     *
     * @return \Illuminate\Support\Collection
     */
    public function getBreadcrumbs(): \Illuminate\Support\Collection
    {
        $breadcrumbs = collect();
        $category = $this;
        
        while ($category) {
            $breadcrumbs->prepend($category);
            $category = $category->parent;
        }
        
        return $breadcrumbs;
    }

    /**
     * 获取层级
     *
     * @return int
     */
    public function getLevel(): int
    {
        return $this->getDepth();
    }

    /**
     * 获取所有后代分类
     *
     * @return \Illuminate\Support\Collection
     */
    public function getDescendants(): \Illuminate\Support\Collection
    {
        $descendants = collect();
        
        $this->children->each(function ($child) use (&$descendants) {
            $descendants->push($child);
            $descendants = $descendants->merge($child->getDescendants());
        });
        
        return $descendants;
    }

    /**
     * 获取同级分类
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getSiblings()
    {
        return static::where('parent_id', $this->parent_id)
            ->where('id', '!=', $this->id)
            ->orderBy('sort')
            ->orderBy('id')
            ->get();
    }

    /**
     * 启用状态查询作用域
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * 根分类查询作用域
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeRoots($query)
    {
        return $query->where('parent_id', 0);
    }

    /**
     * 排序查询作用域
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $direction
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOrdered($query, string $direction = 'asc')
    {
        return $query->orderBy('sort', $direction)->orderBy('id', $direction);
    }

    /**
     * 构建分类树
     *
     * @param int $parentId
     * @return \Illuminate\Support\Collection
     */
    public static function buildTree(int $parentId = 0)
    {
        $categories = static::active()->ordered()->get();
        
        return static::buildTreeFromCollection($categories, $parentId);
    }

    /**
     * 从集合构建分类树
     *
     * @param \Illuminate\Support\Collection $categories
     * @param int $parentId
     * @return \Illuminate\Support\Collection
     */
    protected static function buildTreeFromCollection($categories, int $parentId = 0)
    {
        $branch = collect();

        foreach ($categories->where('parent_id', $parentId) as $category) {
            $children = static::buildTreeFromCollection($categories, $category->id);
            if ($children->isNotEmpty()) {
                $category->setRelation('children', $children);
            }
            $branch->push($category);
        }

        return $branch;
    }
}