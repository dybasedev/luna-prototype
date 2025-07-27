<?php

use Dybasedev\LunaPrototype\Showcase\Structures\Field;

test('字段可以用字符串名称创建', function () {
    $field = new Field('username');
    
    expect($field->key)->toBe('username');
    expect($field->name)->toBe('username');
});

test('字段可以用数组名称创建', function () {
    $field = new Field(['user', 'profile', 'name']);
    
    expect($field->key)->toBe('user-profile-name');
    expect($field->name)->toBe(['user', 'profile', 'name']);
});

test('字段可以用自定义键创建', function () {
    $field = new Field('username', 'custom_key');
    
    expect($field->key)->toBe('custom_key');
    expect($field->name)->toBe('username');
});

test('字段构建器方法正常工作', function () {
    $field = new Field('email')
        ->title('邮箱地址')
        ->description('请输入有效的邮箱地址')
        ->placeholder('example@domain.com')
        ->type('email')
        ->tooltip('邮箱将用于接收通知')
        ->width(200)
        ->hidden(true)
        ->readonly(true);

    expect($field->title)->toBe('邮箱地址');
    expect($field->description)->toBe('请输入有效的邮箱地址');
    expect($field->placeholder)->toBe('example@domain.com');
    expect($field->type)->toBe('email');
    expect($field->tooltip)->toBe('邮箱将用于接收通知');
    expect($field->width)->toBe(200);
    expect($field->hidden)->toBeTrue();
    expect($field->readonly)->toBeTrue();
});

test('字段属性和组件', function () {
    $properties = ['class' => 'form-control', 'data-test' => 'field'];
    $formFieldProperties = ['wrapper-class' => 'form-group'];
    $extendOptions = ['validation' => 'required'];

    $field = new Field('test')
        ->properties($properties)
        ->formFieldProperties($formFieldProperties)
        ->extendOptions($extendOptions)
        ->component('CustomInput')
        ->formFieldComponent('CustomWrapper');

    expect($field->properties)->toBe($properties);
    expect($field->formFieldProperties)->toBe($formFieldProperties);
    expect($field->extendOptions)->toBe($extendOptions);
    expect($field->component)->toBe('CustomInput');
    expect($field->formFieldComponent)->toBe('CustomWrapper');
});

test('静态工厂方法', function () {
    // 测试文本字段工厂方法
    $textField = Field::text('username', '用户名', '请输入用户名');
    expect($textField->type)->toBe('text');
    expect($textField->title)->toBe('用户名');
    expect($textField->placeholder)->toBe('请输入用户名');

    // 测试数字字段工厂方法
    $numberField = Field::number('age', '年龄', '请输入年龄');
    expect($numberField->type)->toBe('number');
    expect($numberField->title)->toBe('年龄');
    expect($numberField->placeholder)->toBe('请输入年龄');

    // 测试密码字段工厂方法
    $passwordField = Field::password('password', '密码', '请输入密码');
    expect($passwordField->type)->toBe('password');
    expect($passwordField->title)->toBe('密码');
    expect($passwordField->placeholder)->toBe('请输入密码');

    // 测试选择字段工厂方法
    $selectField = Field::select('category', '分类');
    expect($selectField->type)->toBe('select');
    expect($selectField->title)->toBe('分类');
});

test('字段链式调用返回同一实例', function () {
    $field = new Field('test');
    
    $result = $field->title('标题')
        ->description('描述')
        ->placeholder('占位符')
        ->type('email')
        ->tooltip('提示')
        ->width(100)
        ->hidden()
        ->readonly();

    expect($result)->toBe($field);
});

test('字段默认值', function () {
    $field = new Field('test');
    
    expect($field->title)->toBeNull();
    expect($field->description)->toBeNull();
    expect($field->placeholder)->toBeNull();
    expect($field->type)->toBe('text');
    expect($field->tooltip)->toBeNull();
    expect($field->width)->toBeNull();
    expect($field->hidden)->toBeFalse();
    expect($field->readonly)->toBeFalse();
    expect($field->properties)->toBe([]);
    expect($field->formFieldProperties)->toBe([]);
    expect($field->extendOptions)->toBe([]);
    expect($field->component)->toBeNull();
    expect($field->formFieldComponent)->toBeNull();
});