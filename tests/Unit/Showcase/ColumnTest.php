<?php

namespace Dybasedev\LunaPrototype\Tests\Unit\Showcase;

use Dybasedev\LunaPrototype\Showcase\Structures\Column;
use Dybasedev\LunaPrototype\Tests\TestCase;

class ColumnTest extends TestCase
{
    public function test_column_inherits_from_field()
    {
        $column = new Column('name');
        
        // 测试继承的基础属性
        $this->assertEquals('name', $column->key);
        $this->assertEquals('name', $column->name);
        $this->assertEquals('text', $column->type);
    }

    public function test_column_specific_properties()
    {
        $column = new Column('title')
            ->sortable(true)
            ->searchable(false)
            ->copyable(true)
            ->ellipsis(false)
            ->order(10)
            ->onlySearch(true);

        $this->assertTrue($column->sortable);
        $this->assertFalse($column->searchable);
        $this->assertTrue($column->copyable);
        $this->assertFalse($column->ellipsis);
        $this->assertEquals(10, $column->order);
        $this->assertTrue($column->onlySearch);
    }

    public function test_column_search_field_properties()
    {
        $searchProperties = ['class' => 'search-input', 'placeholder' => '搜索...'];
        $column = new Column('title')->searchFieldProperties($searchProperties);
        
        $this->assertEquals($searchProperties, $column->searchFieldProperties);
    }

    public function test_column_component_settings()
    {
        $column = new Column('status')->columnComponent('StatusBadge');
        
        $this->assertEquals('StatusBadge', $column->columnComponent);
    }

    public function test_column_builder_methods_return_column_instance()
    {
        $column = new Column('test');
        
        $this->assertInstanceOf(Column::class, $column->sortable());
        $this->assertInstanceOf(Column::class, $column->searchable());
        $this->assertInstanceOf(Column::class, $column->copyable());
        $this->assertInstanceOf(Column::class, $column->ellipsis());
        $this->assertInstanceOf(Column::class, $column->order(1));
        $this->assertInstanceOf(Column::class, $column->searchFieldProperties([]));
        $this->assertInstanceOf(Column::class, $column->columnComponent('Test'));
    }

    public function test_column_static_factory_methods()
    {
        // 测试可排序文本列
        $sortableColumn = Column::sortableText('name', '姓名');
        $this->assertEquals('text', $sortableColumn->type);
        $this->assertEquals('姓名', $sortableColumn->title);
        $this->assertTrue($sortableColumn->sortable);

        // 测试可搜索文本列
        $searchableColumn = Column::searchableText('email', '邮箱');
        $this->assertEquals('text', $searchableColumn->type);
        $this->assertEquals('邮箱', $searchableColumn->title);
        $this->assertTrue($searchableColumn->searchable);

        // 测试仅搜索字段
        $searchOnlyColumn = Column::searchOnly('hidden_field', '隐藏字段');
        $this->assertEquals('text', $searchOnlyColumn->type);
        $this->assertEquals('隐藏字段', $searchOnlyColumn->title);
        $this->assertTrue($searchOnlyColumn->onlySearch);
        $this->assertTrue($searchOnlyColumn->hidden);
    }

    public function test_column_defaults()
    {
        $column = new Column('test');
        
        // 测试 Column 特有的默认值
        $this->assertFalse($column->sortable);
        $this->assertTrue($column->searchable);
        $this->assertFalse($column->copyable);
        $this->assertTrue($column->ellipsis);
        $this->assertNull($column->order);
        $this->assertEquals([], $column->searchFieldProperties);
        $this->assertFalse($column->onlySearch);
        $this->assertNull($column->columnComponent);
    }

    public function test_column_chaining_with_parent_methods()
    {
        $column = new Column('test')
            ->title('测试列')
            ->type('number')
            ->sortable()
            ->searchable(false)
            ->width(150);

        $this->assertEquals('测试列', $column->title);
        $this->assertEquals('number', $column->type);
        $this->assertTrue($column->sortable);
        $this->assertFalse($column->searchable);
        $this->assertEquals(150, $column->width);
    }

    public function test_column_boolean_methods_with_default_true()
    {
        $column = new Column('test');
        
        // 测试不传参数时默认为 true
        $column->sortable();
        $this->assertTrue($column->sortable);
        
        $column->copyable();
        $this->assertTrue($column->copyable);
        
        $column->onlySearch();
        $this->assertTrue($column->onlySearch);
        
        // 测试传入 false
        $column->ellipsis(false);
        $this->assertFalse($column->ellipsis);
    }
}