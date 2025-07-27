<?php

use Dybasedev\LunaPrototype\Showcase\Structures\Column;

test('列继承自字段', function () {
    $column = new Column('name');
    
    // 测试继承的基础属性
    expect($column->key)->toBe('name');
    expect($column->name)->toBe('name');
    expect($column->type)->toBe('text');
});

test('列特有属性', function () {
    $column = new Column('title')
        ->sortable(true)
        ->searchable(false)
        ->copyable(true)
        ->ellipsis(false)
        ->order(10)
        ->onlySearch(true);

    expect($column->sortable)->toBeTrue();
    expect($column->searchable)->toBeFalse();
    expect($column->copyable)->toBeTrue();
    expect($column->ellipsis)->toBeFalse();
    expect($column->order)->toBe(10);
    expect($column->onlySearch)->toBeTrue();
});

test('列搜索字段属性', function () {
    $searchProperties = ['class' => 'search-input', 'placeholder' => '搜索...'];
    $column = new Column('title')->searchFieldProperties($searchProperties);
    
    expect($column->searchFieldProperties)->toBe($searchProperties);
});

test('列组件设置', function () {
    $column = new Column('status')->columnComponent('StatusBadge');
    
    expect($column->columnComponent)->toBe('StatusBadge');
});

test('列构建器方法返回列实例', function () {
    $column = new Column('test');
    
    expect($column->sortable())->toBeInstanceOf(Column::class);
    expect($column->searchable())->toBeInstanceOf(Column::class);
    expect($column->copyable())->toBeInstanceOf(Column::class);
    expect($column->ellipsis())->toBeInstanceOf(Column::class);
    expect($column->order(1))->toBeInstanceOf(Column::class);
    expect($column->searchFieldProperties([]))->toBeInstanceOf(Column::class);
    expect($column->columnComponent('Test'))->toBeInstanceOf(Column::class);
});

test('列静态工厂方法', function () {
    // 测试可排序文本列
    $sortableColumn = Column::sortableText('name', '姓名');
    expect($sortableColumn->type)->toBe('text');
    expect($sortableColumn->title)->toBe('姓名');
    expect($sortableColumn->sortable)->toBeTrue();

    // 测试可搜索文本列
    $searchableColumn = Column::searchableText('email', '邮箱');
    expect($searchableColumn->type)->toBe('text');
    expect($searchableColumn->title)->toBe('邮箱');
    expect($searchableColumn->searchable)->toBeTrue();

    // 测试仅搜索字段
    $searchOnlyColumn = Column::searchOnly('hidden_field', '隐藏字段');
    expect($searchOnlyColumn->type)->toBe('text');
    expect($searchOnlyColumn->title)->toBe('隐藏字段');
    expect($searchOnlyColumn->onlySearch)->toBeTrue();
    expect($searchOnlyColumn->hidden)->toBeTrue();
});

test('列默认值', function () {
    $column = new Column('test');
    
    // 测试 Column 特有的默认值
    expect($column->sortable)->toBeFalse();
    expect($column->searchable)->toBeTrue();
    expect($column->copyable)->toBeFalse();
    expect($column->ellipsis)->toBeTrue();
    expect($column->order)->toBeNull();
    expect($column->searchFieldProperties)->toBe([]);
    expect($column->onlySearch)->toBeFalse();
    expect($column->columnComponent)->toBeNull();
});

test('列链式调用父类方法', function () {
    $column = new Column('test')
        ->title('测试列')
        ->type('number')
        ->sortable()
        ->searchable(false)
        ->width(150);

    expect($column->title)->toBe('测试列');
    expect($column->type)->toBe('number');
    expect($column->sortable)->toBeTrue();
    expect($column->searchable)->toBeFalse();
    expect($column->width)->toBe(150);
});

test('列布尔方法默认为true', function () {
    $column = new Column('test');
    
    // 测试不传参数时默认为 true
    $column->sortable();
    expect($column->sortable)->toBeTrue();
    
    $column->copyable();
    expect($column->copyable)->toBeTrue();
    
    $column->onlySearch();
    expect($column->onlySearch)->toBeTrue();
    
    // 测试传入 false
    $column->ellipsis(false);
    expect($column->ellipsis)->toBeFalse();
});