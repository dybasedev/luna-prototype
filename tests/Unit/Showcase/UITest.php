<?php

use Dybasedev\LunaPrototype\Showcase\Structures\Column;
use Dybasedev\LunaPrototype\Showcase\Structures\Field;
use Dybasedev\LunaPrototype\Showcase\Structures\FieldGroup;
use Dybasedev\LunaPrototype\Showcase\UI;

test('UI可以创建字段', function () {
    $field = UI::field('username');
    
    expect($field)->toBeInstanceOf(Field::class);
    expect($field->key)->toBe('username');
    expect($field->name)->toBe('username');
});

test('UI可以创建带自定义键的字段', function () {
    $field = UI::field('username', 'user_name_key');
    
    expect($field)->toBeInstanceOf(Field::class);
    expect($field->key)->toBe('user_name_key');
    expect($field->name)->toBe('username');
});

test('UI可以创建带数组名称的字段', function () {
    $field = UI::field(['user', 'name']);
    
    expect($field)->toBeInstanceOf(Field::class);
    expect($field->key)->toBe('user-name');
    expect($field->name)->toBe(['user', 'name']);
});

test('UI可以创建字段组', function () {
    $fieldGroup = UI::fieldGroup('用户信息');
    
    expect($fieldGroup)->toBeInstanceOf(FieldGroup::class);
    expect($fieldGroup->key)->toBe('用户信息');
    expect($fieldGroup->name)->toBe('用户信息');
});

test('UI可以创建带自定义键的字段组', function () {
    $fieldGroup = UI::fieldGroup('用户信息', 'user_info');
    
    expect($fieldGroup)->toBeInstanceOf(FieldGroup::class);
    expect($fieldGroup->key)->toBe('user_info');
    expect($fieldGroup->name)->toBe('用户信息');
});

test('UI可以创建列', function () {
    $column = UI::column('姓名');
    
    expect($column)->toBeInstanceOf(Column::class);
    expect($column->key)->toBe('姓名');
    expect($column->name)->toBe('姓名');
});

test('UI可以创建带自定义键的列', function () {
    $column = UI::column('姓名', 'name');
    
    expect($column)->toBeInstanceOf(Column::class);
    expect($column->key)->toBe('name');
    expect($column->name)->toBe('姓名');
});

test('UI可以创建带数组名称的列', function () {
    $column = UI::column(['user', 'profile', 'name']);
    
    expect($column)->toBeInstanceOf(Column::class);
    expect($column->key)->toBe('user-profile-name');
    expect($column->name)->toBe(['user', 'profile', 'name']);
});

test('UI静态方法返回正确的类型', function () {
    // 测试所有静态方法都返回正确的类型
    expect(UI::field('test'))->toBeInstanceOf(Field::class);
    expect(UI::fieldGroup('test'))->toBeInstanceOf(FieldGroup::class);
    expect(UI::column('test'))->toBeInstanceOf(Column::class);
});

test('创建的对象可以链式调用', function () {
    $field = UI::field('email')
        ->title('邮箱')
        ->type('email')
        ->placeholder('请输入邮箱');
    
    expect($field->title)->toBe('邮箱');
    expect($field->type)->toBe('email');
    expect($field->placeholder)->toBe('请输入邮箱');

    $column = UI::column('status')
        ->title('状态')
        ->sortable()
        ->searchable(false);
    
    expect($column->title)->toBe('状态');
    expect($column->sortable)->toBeTrue();
    expect($column->searchable)->toBeFalse();
});