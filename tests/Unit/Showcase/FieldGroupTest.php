<?php

use Dybasedev\LunaPrototype\Showcase\Structures\Field;
use Dybasedev\LunaPrototype\Showcase\Structures\FieldGroup;

test('字段组继承自字段', function () {
    $fieldGroup = new FieldGroup('用户信息');
    
    // 测试继承的基础属性
    expect($fieldGroup->key)->toBe('用户信息');
    expect($fieldGroup->name)->toBe('用户信息');
    expect($fieldGroup->type)->toBe('text');
});

test('字段组以空字段数组开始', function () {
    $fieldGroup = new FieldGroup('测试组');
    
    expect($fieldGroup->fields)->toBe([]);
});

test('字段组可以添加单个字段', function () {
    $fieldGroup = new FieldGroup('用户信息');
    $nameField = new Field('name');
    
    $result = $fieldGroup->field($nameField);
    
    expect($result)->toBe($fieldGroup);
    expect($fieldGroup->fields)->toHaveCount(1);
    expect($fieldGroup->fields[0])->toBe($nameField);
});

test('字段组可以逐个添加多个字段', function () {
    $fieldGroup = new FieldGroup('用户信息');
    $nameField = new Field('name');
    $emailField = new Field('email');
    $phoneField = new Field('phone');
    
    $fieldGroup
        ->field($nameField)
        ->field($emailField)
        ->field($phoneField);
    
    expect($fieldGroup->fields)->toHaveCount(3);
    expect($fieldGroup->fields[0])->toBe($nameField);
    expect($fieldGroup->fields[1])->toBe($emailField);
    expect($fieldGroup->fields[2])->toBe($phoneField);
});

test('字段组可以追加模式添加字段数组', function () {
    $fieldGroup = new FieldGroup('用户信息');
    $nameField = new Field('name');
    $emailField = new Field('email');
    $phoneField = new Field('phone');
    $addressField = new Field('address');
    
    // 先添加一个字段
    $fieldGroup->field($nameField);
    
    // 然后追加字段数组
    $result = $fieldGroup->fields([$emailField, $phoneField], true);
    
    expect($result)->toBe($fieldGroup);
    expect($fieldGroup->fields)->toHaveCount(3);
    expect($fieldGroup->fields[0])->toBe($nameField);
    expect($fieldGroup->fields[1])->toBe($emailField);
    expect($fieldGroup->fields[2])->toBe($phoneField);
    
    // 再次追加
    $fieldGroup->fields([$addressField], true);
    expect($fieldGroup->fields)->toHaveCount(4);
    expect($fieldGroup->fields[3])->toBe($addressField);
});

test('字段组可以替换字段数组', function () {
    $fieldGroup = new FieldGroup('用户信息');
    $nameField = new Field('name');
    $emailField = new Field('email');
    $phoneField = new Field('phone');
    $addressField = new Field('address');
    
    // 先添加一些字段
    $fieldGroup
        ->field($nameField)
        ->field($emailField);
    
    expect($fieldGroup->fields)->toHaveCount(2);
    
    // 替换所有字段
    $fieldGroup->fields([$phoneField, $addressField], false);
    
    expect($fieldGroup->fields)->toHaveCount(2);
    expect($fieldGroup->fields[0])->toBe($phoneField);
    expect($fieldGroup->fields[1])->toBe($addressField);
});

test('字段组追加模式默认为true', function () {
    $fieldGroup = new FieldGroup('测试组');
    $field1 = new Field('field1');
    $field2 = new Field('field2');
    $field3 = new Field('field3');
    
    $fieldGroup->field($field1);
    // 不传 append 参数，默认应该是 true
    $fieldGroup->fields([$field2, $field3]);
    
    expect($fieldGroup->fields)->toHaveCount(3);
    expect($fieldGroup->fields[0])->toBe($field1);
    expect($fieldGroup->fields[1])->toBe($field2);
    expect($fieldGroup->fields[2])->toBe($field3);
});

test('字段组可以使用父类方法', function () {
    $fieldGroup = new FieldGroup('用户信息')
        ->title('用户基本信息')
        ->description('请填写用户的基本信息');
    
    expect($fieldGroup->title)->toBe('用户基本信息');
    expect($fieldGroup->description)->toBe('请填写用户的基本信息');
});

test('字段组保持插入顺序', function () {
    $fieldGroup = new FieldGroup('测试组');
    $fields = [];
    
    // 创建多个字段并按顺序添加
    for ($i = 1; $i <= 5; $i++) {
        $field = new Field("field{$i}");
        $fields[] = $field;
        $fieldGroup->field($field);
    }
    
    // 验证顺序
    for ($i = 0; $i < 5; $i++) {
        expect($fieldGroup->fields[$i])->toBe($fields[$i]);
    }
});

test('字段组空数组处理', function () {
    $fieldGroup = new FieldGroup('测试组');
    $originalField = new Field('original');
    
    $fieldGroup->field($originalField);
    
    // 追加空数组
    $fieldGroup->fields([], true);
    expect($fieldGroup->fields)->toHaveCount(1);
    expect($fieldGroup->fields[0])->toBe($originalField);
    
    // 替换为空数组
    $fieldGroup->fields([], false);
    expect($fieldGroup->fields)->toHaveCount(0);
    expect($fieldGroup->fields)->toBe([]);
});