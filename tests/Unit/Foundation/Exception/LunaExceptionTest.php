<?php

use Dybasedev\LunaPrototype\Foundation\Exception\LunaException;
use Dybasedev\LunaPrototype\Foundation\Exception\LunaExceptionMapperBuilder;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

it('可以使用消息创建 Luna 异常', function () {
    $exception = LunaException::create('Test error message');
    
    expect($exception)->toBeInstanceOf(LunaException::class);
    expect($exception->getMessage())->toBe('Test error message');
});

it('可以使用错误码创建 Luna 异常', function () {
    $exception = LunaException::create('Test error', 500);
    
    expect($exception->getCode())->toBe(500);
    expect($exception->getMessage())->toBe('Test error');
});

it('可以使用数据创建 Luna 异常', function () {
    $data = ['field' => 'username', 'value' => 'test'];
    $exception = LunaException::create('Validation error')
        ->withData($data);
    
    expect($exception->data)->toBe($data);
});

it('可以设置显示消息', function () {
    $exception = LunaException::create('Internal error')
        ->withDisplayMessage('Something went wrong');
    
    expect($exception->displayMessage)->toBe('Something went wrong');
});

it('可以设置 HTTP 状态码', function () {
    $exception = LunaException::create('Unauthorized')
        ->withHttpStatus(Response::HTTP_UNAUTHORIZED);
    
    expect($exception->httpStatus)->toBe(Response::HTTP_UNAUTHORIZED);
});

it('可以设置行为', function () {
    $behaviour = ['action' => 'redirect', 'url' => '/login'];
    $exception = LunaException::create('Authentication required')
        ->withBehaviour($behaviour);
    
    expect($exception->behaviour)->toBe($behaviour);
});

it('可以从 Throwable 创建', function () {
    $original = new \Exception('Original exception', 123);
    $exception = LunaException::create($original);
    
    expect($exception->getMessage())->toBe('Original exception');
    expect($exception->getCode())->toBe(123);
    expect($exception->getPrevious())->toBe($original);
});

it('可以使用异常映射构建器', function () {
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

it('可以使用闭包构建映射器', function () {
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