<?php

namespace Dybasedev\LunaPrototype\Permission\Http\Middleware;

use Closure;
use Dybasedev\LunaPrototype\Permission\PermissionSubject;
use Dybasedev\LunaPrototype\Permission\Support\PermissionChecker;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 权限检查中间件
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
     * @param string|null $guard
     * @return Response
     */
    public function handle(Request $request, Closure $next, string $action, string $resource, ?string $guard = null): Response
    {
        $user = $guard ? auth()->guard($guard)->user() : auth()->user();
        
        if (!$user instanceof PermissionSubject) {
            abort(403, '用户未实现权限接口');
        }
        
        // 构建上下文
        $context = $this->buildContext($request, $resource);
        
        // 检查权限
        $checker = PermissionChecker::make($user)->withContext($context);
        
        if ($checker->cannot($action, $resource)) {
            abort(403, '无权执行此操作');
        }
        
        return $next($request);
    }

    /**
     * 构建上下文
     *
     * @param Request $request
     * @param string $resource
     * @return array
     */
    protected function buildContext(Request $request, string $resource): array
    {
        $context = [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ];
        
        // 从路由参数中提取资源ID
        $routeParams = $request->route()->parameters();
        
        // 尝试匹配资源相关的参数
        foreach ($routeParams as $key => $value) {
            if (str_contains($resource, $key) || $key === 'id') {
                $context['resource_id'] = $value;
                
                // 如果是模型实例，提取更多信息
                if (is_object($value) && method_exists($value, 'getKey')) {
                    $context['resource_id'] = $value->getKey();
                    
                    // 提取所有者信息
                    foreach (['user_id', 'owner_id', 'created_by'] as $field) {
                        if (isset($value->{$field})) {
                            $context['resource_owner'] = $value->{$field};
                            break;
                        }
                    }
                }
                
                break;
            }
        }
        
        return $context;
    }
}