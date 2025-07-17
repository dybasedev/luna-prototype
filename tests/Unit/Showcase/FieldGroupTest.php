<?php

namespace Dybasedev\LunaPrototype\Tests\Unit\Showcase;

use Dybasedev\LunaPrototype\Showcase\Structures\Field;
use Dybasedev\LunaPrototype\Showcase\Structures\FieldGroup;
use Dybasedev\LunaPrototype\Tests\TestCase;

class FieldGroupTest extends TestCase
{
    public function test_field_group_inherits_from_field()
    {
        $fieldGroup = new FieldGroup('用户信息');
        
        // 测试继承的基础属性
        $this->assertEquals('用户信息', $fieldGroup->key);
        $this->assertEquals('用户信息', $fieldGroup->name);
        $this->assertEquals('text', $fieldGroup->type);
    }

    public function test_field_group_starts_with_empty_fields()
    {
        $fieldGroup = new FieldGroup('测试组');
        
        $this->assertEquals([], $fieldGroup->fields);
    }

    public function test_field_group_can_add_single_field()
    {
        $fieldGroup = new FieldGroup('用户信息');
        $nameField = new Field('name');
        
        $result = $fieldGroup->field($nameField);
        
        $this->assertSame($fieldGroup, $result);
        $this->assertCount(1, $fieldGroup->fields);
        $this->assertSame($nameField, $fieldGroup->fields[0]);
    }

    public function test_field_group_can_add_multiple_fields_one_by_one()
    {
        $fieldGroup = new FieldGroup('用户信息');
        $nameField = new Field('name');
        $emailField = new Field('email');
        $phoneField = new Field('phone');
        
        $fieldGroup
            ->field($nameField)
            ->field($emailField)
            ->field($phoneField);
        
        $this->assertCount(3, $fieldGroup->fields);
        $this->assertSame($nameField, $fieldGroup->fields[0]);
        $this->assertSame($emailField, $fieldGroup->fields[1]);
        $this->assertSame($phoneField, $fieldGroup->fields[2]);
    }

    public function test_field_group_can_add_fields_array_append_mode()
    {
        $fieldGroup = new FieldGroup('用户信息');
        $nameField = new Field('name');
        $emailField = new Field('email');
        $phoneField = new Field('phone');
        $addressField = new Field('address');
        
        // 先添加一个字段
        $fieldGroup->field($nameField);
        
        // 然后追加字段数组
        $result = $fieldGroup->fields([$emailField, $phoneField], true);
        
        $this->assertSame($fieldGroup, $result);
        $this->assertCount(3, $fieldGroup->fields);
        $this->assertSame($nameField, $fieldGroup->fields[0]);
        $this->assertSame($emailField, $fieldGroup->fields[1]);
        $this->assertSame($phoneField, $fieldGroup->fields[2]);
        
        // 再次追加
        $fieldGroup->fields([$addressField], true);
        $this->assertCount(4, $fieldGroup->fields);
        $this->assertSame($addressField, $fieldGroup->fields[3]);
    }

    public function test_field_group_can_replace_fields_array()
    {
        $fieldGroup = new FieldGroup('用户信息');
        $nameField = new Field('name');
        $emailField = new Field('email');
        $phoneField = new Field('phone');
        $addressField = new Field('address');
        
        // 先添加一些字段
        $fieldGroup
            ->field($nameField)
            ->field($emailField);
        
        $this->assertCount(2, $fieldGroup->fields);
        
        // 替换所有字段
        $fieldGroup->fields([$phoneField, $addressField], false);
        
        $this->assertCount(2, $fieldGroup->fields);
        $this->assertSame($phoneField, $fieldGroup->fields[0]);
        $this->assertSame($addressField, $fieldGroup->fields[1]);
    }

    public function test_field_group_append_mode_default_is_true()
    {
        $fieldGroup = new FieldGroup('测试组');
        $field1 = new Field('field1');
        $field2 = new Field('field2');
        $field3 = new Field('field3');
        
        $fieldGroup->field($field1);
        // 不传 append 参数，默认应该是 true
        $fieldGroup->fields([$field2, $field3]);
        
        $this->assertCount(3, $fieldGroup->fields);
        $this->assertSame($field1, $fieldGroup->fields[0]);
        $this->assertSame($field2, $fieldGroup->fields[1]);
        $this->assertSame($field3, $fieldGroup->fields[2]);
    }

    public function test_field_group_can_be_used_with_parent_methods()
    {
        $fieldGroup = new FieldGroup('用户信息')
            ->title('用户基本信息')
            ->description('请填写用户的基本信息');
        
        $this->assertEquals('用户基本信息', $fieldGroup->title);
        $this->assertEquals('请填写用户的基本信息', $fieldGroup->description);
    }

    public function test_field_group_maintains_insertion_order()
    {
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
            $this->assertSame($fields[$i], $fieldGroup->fields[$i]);
        }
    }

    public function test_field_group_empty_fields_array_handling()
    {
        $fieldGroup = new FieldGroup('测试组');
        $originalField = new Field('original');
        
        $fieldGroup->field($originalField);
        
        // 追加空数组
        $fieldGroup->fields([], true);
        $this->assertCount(1, $fieldGroup->fields);
        $this->assertSame($originalField, $fieldGroup->fields[0]);
        
        // 替换为空数组
        $fieldGroup->fields([], false);
        $this->assertCount(0, $fieldGroup->fields);
        $this->assertEquals([], $fieldGroup->fields);
    }
}