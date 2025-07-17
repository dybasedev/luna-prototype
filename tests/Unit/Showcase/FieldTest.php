<?php

namespace Dybasedev\LunaPrototype\Tests\Unit\Showcase;

use Dybasedev\LunaPrototype\Showcase\Structures\Field;
use Dybasedev\LunaPrototype\Tests\TestCase;

class FieldTest extends TestCase
{
    public function test_field_can_be_created_with_string_name()
    {
        $field = new Field('username');
        
        $this->assertEquals('username', $field->key);
        $this->assertEquals('username', $field->name);
    }

    public function test_field_can_be_created_with_array_name()
    {
        $field = new Field(['user', 'profile', 'name']);
        
        $this->assertEquals('user-profile-name', $field->key);
        $this->assertEquals(['user', 'profile', 'name'], $field->name);
    }

    public function test_field_can_be_created_with_custom_key()
    {
        $field = new Field('username', 'custom_key');
        
        $this->assertEquals('custom_key', $field->key);
        $this->assertEquals('username', $field->name);
    }

    public function test_field_builder_methods_work_correctly()
    {
        $field = new Field('email')
            ->title('邮箱地址')
            ->description('请输入有效的邮箱地址')
            ->placeholder('example@domain.com')
            ->type('email')
            ->tooltip('邮箱将用于接收通知')
            ->width(200)
            ->hidden(true)
            ->readonly(true);

        $this->assertEquals('邮箱地址', $field->title);
        $this->assertEquals('请输入有效的邮箱地址', $field->description);
        $this->assertEquals('example@domain.com', $field->placeholder);
        $this->assertEquals('email', $field->type);
        $this->assertEquals('邮箱将用于接收通知', $field->tooltip);
        $this->assertEquals(200, $field->width);
        $this->assertTrue($field->hidden);
        $this->assertTrue($field->readonly);
    }

    public function test_field_properties_and_components()
    {
        $properties = ['class' => 'form-control', 'data-test' => 'field'];
        $formFieldProperties = ['wrapper-class' => 'form-group'];
        $extendOptions = ['validation' => 'required'];

        $field = new Field('test')
            ->properties($properties)
            ->formFieldProperties($formFieldProperties)
            ->extendOptions($extendOptions)
            ->component('CustomInput')
            ->formFieldComponent('CustomWrapper');

        $this->assertEquals($properties, $field->properties);
        $this->assertEquals($formFieldProperties, $field->formFieldProperties);
        $this->assertEquals($extendOptions, $field->extendOptions);
        $this->assertEquals('CustomInput', $field->component);
        $this->assertEquals('CustomWrapper', $field->formFieldComponent);
    }

    public function test_static_factory_methods()
    {
        // 测试文本字段工厂方法
        $textField = Field::text('username', '用户名', '请输入用户名');
        $this->assertEquals('text', $textField->type);
        $this->assertEquals('用户名', $textField->title);
        $this->assertEquals('请输入用户名', $textField->placeholder);

        // 测试数字字段工厂方法
        $numberField = Field::number('age', '年龄', '请输入年龄');
        $this->assertEquals('number', $numberField->type);
        $this->assertEquals('年龄', $numberField->title);
        $this->assertEquals('请输入年龄', $numberField->placeholder);

        // 测试密码字段工厂方法
        $passwordField = Field::password('password', '密码', '请输入密码');
        $this->assertEquals('password', $passwordField->type);
        $this->assertEquals('密码', $passwordField->title);
        $this->assertEquals('请输入密码', $passwordField->placeholder);

        // 测试选择字段工厂方法
        $selectField = Field::select('category', '分类');
        $this->assertEquals('select', $selectField->type);
        $this->assertEquals('分类', $selectField->title);
    }

    public function test_field_chaining_returns_same_instance()
    {
        $field = new Field('test');
        
        $result = $field->title('标题')
            ->description('描述')
            ->placeholder('占位符')
            ->type('email')
            ->tooltip('提示')
            ->width(100)
            ->hidden()
            ->readonly();

        $this->assertSame($field, $result);
    }

    public function test_field_defaults()
    {
        $field = new Field('test');
        
        $this->assertNull($field->title);
        $this->assertNull($field->description);
        $this->assertNull($field->placeholder);
        $this->assertEquals('text', $field->type);
        $this->assertNull($field->tooltip);
        $this->assertNull($field->width);
        $this->assertFalse($field->hidden);
        $this->assertFalse($field->readonly);
        $this->assertEquals([], $field->properties);
        $this->assertEquals([], $field->formFieldProperties);
        $this->assertEquals([], $field->extendOptions);
        $this->assertNull($field->component);
        $this->assertNull($field->formFieldComponent);
    }
}