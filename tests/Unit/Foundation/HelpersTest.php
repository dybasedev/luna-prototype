<?php

it('为相同字符串生成一致的哈希码', function () {
    $string = 'test-string';
    
    expect(hash_code($string))->toBe(hash_code($string));
    expect(short_hash_code($string))->toBe(short_hash_code($string));
});

it('为不同字符串生成不同的哈希码', function () {
    expect(hash_code('string1'))->not->toBe(hash_code('string2'));
    expect(short_hash_code('string1'))->not->toBe(short_hash_code('string2'));
});

it('生成整数哈希码', function () {
    expect(hash_code('test'))->toBeInt();
    expect(short_hash_code('test'))->toBeInt();
});

it('生成在预期范围内的短哈希码', function () {
    expect(short_hash_code('test'))->toBeLessThan(255);
    expect(short_hash_code('test'))->toBeGreaterThanOrEqual(0);
});