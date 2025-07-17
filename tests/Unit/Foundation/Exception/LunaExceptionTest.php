<?php

use Dybasedev\LunaPrototype\Foundation\Exception\LunaException;
use Dybasedev\LunaPrototype\Foundation\Exception\LunaExceptionMapperBuilder;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

it('can create luna exception with message', function () {
    $exception = LunaException::create('Test error message');
    
    expect($exception)->toBeInstanceOf(LunaException::class);
    expect($exception->getMessage())->toBe('Test error message');
});

it('can create luna exception with code', function () {
    $exception = LunaException::create('Test error', 500);
    
    expect($exception->getCode())->toBe(500);
    expect($exception->getMessage())->toBe('Test error');
});

it('can create luna exception with data', function () {
    $data = ['field' => 'username', 'value' => 'test'];
    $exception = LunaException::create('Validation error')
        ->withData($data);
    
    expect($exception->data)->toBe($data);
});

it('can set display message', function () {
    $exception = LunaException::create('Internal error')
        ->withDisplayMessage('Something went wrong');
    
    expect($exception->displayMessage)->toBe('Something went wrong');
});

it('can set http status', function () {
    $exception = LunaException::create('Unauthorized')
        ->withHttpStatus(Response::HTTP_UNAUTHORIZED);
    
    expect($exception->httpStatus)->toBe(Response::HTTP_UNAUTHORIZED);
});

it('can set behaviour', function () {
    $behaviour = ['action' => 'redirect', 'url' => '/login'];
    $exception = LunaException::create('Authentication required')
        ->withBehaviour($behaviour);
    
    expect($exception->behaviour)->toBe($behaviour);
});

it('can be created from throwable', function () {
    $original = new \Exception('Original exception', 123);
    $exception = LunaException::create($original);
    
    expect($exception->getMessage())->toBe('Original exception');
    expect($exception->getCode())->toBe(123);
    expect($exception->getPrevious())->toBe($original);
});

it('can use exception mapper builder', function () {
    $builder = new LunaExceptionMapperBuilder(\Exception::class);
    
    $builder
        ->message('Validation failed')
        ->httpStatus(Response::HTTP_BAD_REQUEST)
        ->data(['field' => 'username']);
    
    $mapper = $builder->build();
    
    expect($mapper)->toBeInstanceOf(\Closure::class);
    
    $exception = new \Exception('Original message');
    $result = $mapper($exception);
    
    expect($result)->toHaveKeys(['message', 'httpStatus', 'data', 'behaviour', 'report']);
    expect($result['message'])->toBe('Validation failed');
    expect($result['httpStatus'])->toBe(Response::HTTP_BAD_REQUEST);
    expect($result['data'])->toBe(['field' => 'username']);
});

it('can build mapper with closures', function () {
    $builder = new LunaExceptionMapperBuilder(\Exception::class);
    
    $builder
        ->message(fn(\Exception $e) => 'Custom: ' . $e->getMessage())
        ->httpStatus(fn(\Exception $e) => $e->getCode() ?: 500)
        ->data(fn(\Exception $e) => ['original_code' => $e->getCode()])
        ->dontReport();
    
    $mapper = $builder->build();
    $exception = new \Exception('Test message', 404);
    $result = $mapper($exception);
    
    expect($result['message'])->toBe('Custom: Test message');
    expect($result['httpStatus'])->toBe(404);
    expect($result['data'])->toBe(['original_code' => 404]);
    expect($result['report'])->toBeFalse();
});