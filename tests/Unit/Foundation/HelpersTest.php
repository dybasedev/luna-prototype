<?php

it('generates consistent hash codes for same strings', function () {
    $string = 'test-string';
    
    expect(hash_code($string))->toBe(hash_code($string));
    expect(short_hash_code($string))->toBe(short_hash_code($string));
});

it('generates different hash codes for different strings', function () {
    expect(hash_code('string1'))->not->toBe(hash_code('string2'));
    expect(short_hash_code('string1'))->not->toBe(short_hash_code('string2'));
});

it('generates integer hash codes', function () {
    expect(hash_code('test'))->toBeInt();
    expect(short_hash_code('test'))->toBeInt();
});

it('generates short hash codes within expected range', function () {
    expect(short_hash_code('test'))->toBeLessThan(255);
    expect(short_hash_code('test'))->toBeGreaterThanOrEqual(0);
});