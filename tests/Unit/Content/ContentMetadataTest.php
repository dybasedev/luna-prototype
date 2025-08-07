<?php

use Dybasedev\LunaPrototype\Content\Models\Content;
use Dybasedev\LunaPrototype\Content\Models\ContentMetadata;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function () {
    // 运行迁移
    $migration = include __DIR__ . '/../../../src/Content/migrations/0001_01_01_000000_create_luna_prototype_content_tables.php';
    $migration->up();
    
    $this->content = Content::create([
        'name' => 'metadata-test-content',
        'title' => '元数据测试内容',
        'payload' => [],
    ]);
});

test('可以创建不同类型的元数据', function () {
    // 字符串类型
    $stringMeta = ContentMetadata::createFor($this->content->id, 'author', '张三');
    expect($stringMeta->type)->toBe(ContentMetadata::TYPE_STRING);
    expect($stringMeta->string_value)->toBe('张三');
    expect($stringMeta->getTypedValue())->toBe('张三');

    // 整数类型
    $intMeta = ContentMetadata::createFor($this->content->id, 'views', 1000);
    expect($intMeta->type)->toBe(ContentMetadata::TYPE_INTEGER);
    expect($intMeta->integer_value)->toBe(1000);
    expect($intMeta->getTypedValue())->toBe(1000);

    // 浮点数类型
    $floatMeta = ContentMetadata::createFor($this->content->id, 'rating', 4.5);
    expect($floatMeta->type)->toBe(ContentMetadata::TYPE_FLOAT);
    expect($floatMeta->float_value)->toBe(4.5);
    expect($floatMeta->getTypedValue())->toBe(4.5);

    // 布尔类型
    $boolMeta = ContentMetadata::createFor($this->content->id, 'featured', true);
    expect($boolMeta->type)->toBe(ContentMetadata::TYPE_BOOLEAN);
    expect($boolMeta->boolean_value)->toBe(true);
    expect($boolMeta->getTypedValue())->toBe(true);

    // JSON类型
    $jsonData = ['tags' => ['测试', '元数据'], 'count' => 2];
    $jsonMeta = ContentMetadata::createFor($this->content->id, 'tags', $jsonData);
    expect($jsonMeta->type)->toBe(ContentMetadata::TYPE_JSON);
    expect($jsonMeta->json_value)->toBe($jsonData);
    expect($jsonMeta->getTypedValue())->toBe($jsonData);

    // 日期时间类型
    $datetime = '2023-12-01 10:00:00';
    $datetimeMeta = ContentMetadata::createFor($this->content->id, 'publish_date', $datetime);
    expect($datetimeMeta->type)->toBe(ContentMetadata::TYPE_DATETIME);
    expect($datetimeMeta->datetime_value)->toBeInstanceOf(Carbon::class);
    expect($datetimeMeta->getTypedValue())->toBeInstanceOf(Carbon::class);
});

test('可以自动检测数据类型', function () {
    // 测试自动类型检测
    $meta1 = ContentMetadata::createFor($this->content->id, 'auto_string', 'text');
    expect($meta1->type)->toBe(ContentMetadata::TYPE_STRING);

    $meta2 = ContentMetadata::createFor($this->content->id, 'auto_int', 123);
    expect($meta2->type)->toBe(ContentMetadata::TYPE_INTEGER);

    $meta3 = ContentMetadata::createFor($this->content->id, 'auto_float', 3.14);
    expect($meta3->type)->toBe(ContentMetadata::TYPE_FLOAT);

    $meta4 = ContentMetadata::createFor($this->content->id, 'auto_bool', false);
    expect($meta4->type)->toBe(ContentMetadata::TYPE_BOOLEAN);

    $meta5 = ContentMetadata::createFor($this->content->id, 'auto_array', ['a', 'b']);
    expect($meta5->type)->toBe(ContentMetadata::TYPE_JSON);

    $meta6 = ContentMetadata::createFor($this->content->id, 'auto_object', (object)['key' => 'value']);
    expect($meta6->type)->toBe(ContentMetadata::TYPE_JSON);
});

test('可以更新元数据值', function () {
    $meta = ContentMetadata::createFor($this->content->id, 'updateable', 'initial');
    
    // 更新为不同类型的值
    $meta->setTypedValue(100);
    $meta->save();
    
    expect($meta->fresh()->type)->toBe(ContentMetadata::TYPE_INTEGER);
    expect($meta->fresh()->getTypedValue())->toBe(100);
    
    // 再次更新
    $meta->setTypedValue(['updated' => true]);
    $meta->save();
    
    expect($meta->fresh()->type)->toBe(ContentMetadata::TYPE_JSON);
    expect($meta->fresh()->getTypedValue())->toBe(['updated' => true]);
});

test('元数据键在同一内容中必须唯一', function () {
    ContentMetadata::createFor($this->content->id, 'unique_key', 'value1');
    
    // 尝试创建相同键的元数据应该失败
    expect(fn() => ContentMetadata::createFor($this->content->id, 'unique_key', 'value2'))
        ->toThrow(\Illuminate\Database\QueryException::class);
    
    // 但不同内容可以有相同的键
    $anotherContent = Content::create([
        'name' => 'another-content',
        'title' => '另一个内容',
        'payload' => [],
    ]);
    
    $differentContent = ContentMetadata::createFor($anotherContent->id, 'unique_key', 'value3');
    expect($differentContent)->toBeInstanceOf(ContentMetadata::class);
});

test('可以获取特定类型的元数据', function () {
    // 创建各种类型的元数据
    ContentMetadata::createFor($this->content->id, 'str1', 'string1');
    ContentMetadata::createFor($this->content->id, 'str2', 'string2');
    ContentMetadata::createFor($this->content->id, 'int1', 100);
    ContentMetadata::createFor($this->content->id, 'int2', 200);
    ContentMetadata::createFor($this->content->id, 'bool1', true);
    
    // 按类型查询
    $strings = ContentMetadata::where('content_id', $this->content->id)
        ->where('type', ContentMetadata::TYPE_STRING)
        ->get();
    expect($strings)->toHaveCount(2);
    
    $integers = ContentMetadata::where('content_id', $this->content->id)
        ->where('type', ContentMetadata::TYPE_INTEGER)
        ->get();
    expect($integers)->toHaveCount(2);
    
    $booleans = ContentMetadata::where('content_id', $this->content->id)
        ->where('type', ContentMetadata::TYPE_BOOLEAN)
        ->get();
    expect($booleans)->toHaveCount(1);
});

test('大文本值的存储', function () {
    $longText = str_repeat('这是一段很长的文本。', 100); // 生成长文本
    
    $meta = ContentMetadata::createFor($this->content->id, 'long_text', $longText);
    
    expect($meta->string_value)->toBe($longText);
    expect($meta->getTypedValue())->toBe($longText);
    expect(strlen($meta->string_value))->toBeGreaterThan(500);
});

test('特殊值的处理', function () {
    // 空字符串
    $emptyString = ContentMetadata::createFor($this->content->id, 'empty_string', '');
    expect($emptyString->getTypedValue())->toBe('');
    
    // 零值
    $zero = ContentMetadata::createFor($this->content->id, 'zero', 0);
    expect($zero->getTypedValue())->toBe(0);
    
    // 空数组
    $emptyArray = ContentMetadata::createFor($this->content->id, 'empty_array', []);
    expect($emptyArray->getTypedValue())->toBe([]);
    
    // null 值被处理为字符串类型
    $nullValue = ContentMetadata::createFor($this->content->id, 'null_value', null);
    expect($nullValue->type)->toBe(ContentMetadata::TYPE_STRING);
    expect($nullValue->getTypedValue())->toBe('');
});

test('日期时间格式的处理', function () {
    // 各种日期时间格式
    $formats = [
        '2023-12-01',
        '2023-12-01 15:30:00',
        '2023-12-01T15:30:00Z',
        now(),
    ];
    
    foreach ($formats as $index => $datetime) {
        $meta = ContentMetadata::createFor($this->content->id, "datetime_{$index}", $datetime);
        expect($meta->type)->toBe(ContentMetadata::TYPE_DATETIME, "Failed for format index {$index}: " . (is_object($datetime) ? get_class($datetime) : $datetime));
        expect($meta->getTypedValue())->toBeInstanceOf(Carbon::class);
    }
});

test('通过内容模型管理元数据', function () {
    // 使用内容模型的便捷方法
    $this->content->setMetadata('via_model', 'test value');
    
    expect($this->content->getMetadata('via_model'))->toBe('test value');
    
    // 更新元数据
    $this->content->setMetadata('via_model', 'updated value');
    
    expect($this->content->getMetadata('via_model'))->toBe('updated value');
    
    // 获取不存在的元数据
    expect($this->content->getMetadata('non_existent', 'default'))->toBe('default');
});