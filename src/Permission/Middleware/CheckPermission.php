<?php

namespace Dybasedev\LunaPrototype\Permission\Middleware;

use Closure;
use Dybasedev\LunaPrototype\Foundation\Exception\LunaException;
use Dybasedev\LunaPrototype\Permission\PermissionSubject;
use Illuminate\Http\Request;

/**
 * 权限检查中间件
 * 
 * 使用示例：
 * Route::middleware('permission:read,users')->get('/users', ...);
 * Route::middleware('permission:*,admin.*')->get('/admin', ...);
 */
class CheckPermission
{
    /**
     * 处理请求
     *
     * @param Request $request
     * @param Closure $next
     * @param string $action
     * @param string $resource
     * @return mixed
     * @throws LunaException
     */
    public function handle(Request $request, Closure $next, string $action, string $resource)
    {
        $user = $request->user();

        if (!$user || !$user instanceof PermissionSubject) {
            throw LunaException::create('未登录或用户未实现权限接口')
                ->withDisplayMessage('请先登录')
                ->withHttpStatus(401);
        }

        // 构建上下文
        $context = $this->buildContext($request);

        // 检查权限
        if (!permission()->check($user, $action, $resource, $context)) {
            throw LunaException::create('权限不足')
                ->withDisplayMessage('您没有权限执行此操作')
                ->withHttpStatus(403);
        }

        return $next($request);
    }

    /**
     * 构建权限检查上下文
     *
     * @param Request $request
     * @return array
     */
    protected function buildContext(Request $request): array
    {
        return [
            'ip' => $request->ip(),
            'method' => $request->method(),
            'path' => $request->path(),
            'user_agent' => $request->userAgent(),
            'route_name' => $request->route()?->getName(),
            'route_parameters' => $request->route()?->parameters() ?? [],
        ];
    }
}