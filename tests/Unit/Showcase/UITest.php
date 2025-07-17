<?php

namespace Dybasedev\LunaPrototype\Tests\Unit\Showcase;

use Dybasedev\LunaPrototype\Showcase\Structures\Column;
use Dybasedev\LunaPrototype\Showcase\Structures\Field;
use Dybasedev\LunaPrototype\Showcase\Structures\FieldGroup;
use Dybasedev\LunaPrototype\Showcase\UI;
use Dybasedev\LunaPrototype\Tests\TestCase;

class UITest extends TestCase
{
    public function test_ui_can_create_field()
    {
        $field = UI::field('username');
        
        $this->assertInstanceOf(Field::class, $field);
        $this->assertEquals('username', $field->key);
        $this->assertEquals('username', $field->name);
    }

    public function test_ui_can_create_field_with_custom_key()
    {
        $field = UI::field('username', 'user_name_key');
        
        $this->assertInstanceOf(Field::class, $field);
        $this->assertEquals('user_name_key', $field->key);
        $this->assertEquals('username', $field->name);
    }

    public function test_ui_can_create_field_with_array_name()
    {
        $field = UI::field(['user', 'name']);
        
        $this->assertInstanceOf(Field::class, $field);
        $this->assertEquals('user-name', $field->key);
        $this->assertEquals(['user', 'name'], $field->name);
    }

    public function test_ui_can_create_field_group()
    {
        $fieldGroup = UI::fieldGroup('用户信息');
        
        $this->assertInstanceOf(FieldGroup::class, $fieldGroup);
        $this->assertEquals('用户信息', $fieldGroup->key);
        $this->assertEquals('用户信息', $fieldGroup->name);
    }

    public function test_ui_can_create_field_group_with_custom_key()
    {
        $fieldGroup = UI::fieldGroup('用户信息', 'user_info');
        
        $this->assertInstanceOf(FieldGroup::class, $fieldGroup);
        $this->assertEquals('user_info', $fieldGroup->key);
        $this->assertEquals('用户信息', $fieldGroup->name);
    }

    public function test_ui_can_create_column()
    {
        $column = UI::column('姓名');
        
        $this->assertInstanceOf(Column::class, $column);
        $this->assertEquals('姓名', $column->key);
        $this->assertEquals('姓名', $column->name);
    }

    public function test_ui_can_create_column_with_custom_key()
    {
        $column = UI::column('姓名', 'name');
        
        $this->assertInstanceOf(Column::class, $column);
        $this->assertEquals('name', $column->key);
        $this->assertEquals('姓名', $column->name);
    }

    public function test_ui_can_create_column_with_array_name()
    {
        $column = UI::column(['user', 'profile', 'name']);
        
        $this->assertInstanceOf(Column::class, $column);
        $this->assertEquals('user-profile-name', $column->key);
        $this->assertEquals(['user', 'profile', 'name'], $column->name);
    }

    public function test_ui_static_methods_return_correct_types()
    {
        // 测试所有静态方法都返回正确的类型
        $this->assertInstanceOf(Field::class, UI::field('test'));
        $this->assertInstanceOf(FieldGroup::class, UI::fieldGroup('test'));
        $this->assertInstanceOf(Column::class, UI::column('test'));
    }

    public function test_created_objects_can_be_chained()
    {
        $field = UI::field('email')
            ->title('邮箱')
            ->type('email')
            ->placeholder('请输入邮箱');
        
        $this->assertEquals('邮箱', $field->title);
        $this->assertEquals('email', $field->type);
        $this->assertEquals('请输入邮箱', $field->placeholder);

        $column = UI::column('status')
            ->title('状态')
            ->sortable()
            ->searchable(false);
        
        $this->assertEquals('状态', $column->title);
        $this->assertTrue($column->sortable);
        $this->assertFalse($column->searchable);
    }
}