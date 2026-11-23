<?php
declare(strict_types=1);

namespace App\Core;

use Throwable;

/**
 * Tiny router. Register routes in config/routes/<yourname>.php:
 *
 *   return function (Router $router): void {
 *       $router->get('/api/v1/categories', [CategoryController::class, 'index']);
 *       $router->post('/api/v1/cart/items', [CartController::class, 'add'], [[AuthMiddleware::class, 'requireLogin']]);
 *       $router->get('/api/v1/items/{id}', fn(array $p) => Response::success(['id' => $p['id']]));
 *   };
 *
 * Handler  = closure or [ClassName::class, 'method'] (method is static or the class has a no-arg constructor).
 * Middleware = list of the same kind of callables, run in order before the handler.
 * Handlers receive an array of URL parameters ({id} => $params['id']).
 */
final class Router
{
    private array $routes = [];

    public function get(string $path, callable|array $handler, array $middleware = []): void    { $this->add('GET', $path, $handler, $middleware); }
    public function post(string $path, callable|array $handler, array $middleware = []): void   { $this->add('POST', $path, $handler, $middleware); }
    public function put(string $path, callable|array $handler, array $middleware = []): void    { $this->add('PUT', $path, $handler, $middleware); }
    public function delete(string $path, callable|array $handler, array $middleware = []): void { $this->add('DELETE', $path, $handler, $middleware); }

    private function add(string $method, string $path, callable|array $handler, array $middleware): void
    {
        $regex = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>[^/]+)', rtrim($path, '/') ?: '/') . '$#';
        $this->routes[] = compact('method', 'regex', 'handler', 'middleware');
    }

    public function dispatch(string $method, string $uri): void
    {
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';

        // Works when the app lives in a subfolder (e.g. /campus-platform/public)
        $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
        if ($base !== '' && str_starts_with($path, $base)) {
            $path = substr($path, strlen($base));
        }
        $path = rtrim($path, '/') ?: '/';
        $isApi = str_starts_with($path, '/api/');

        try {
            $pathMatched = false;
            foreach ($this->routes as $route) {
                if (!preg_match($route['regex'], $path, $m)) {
                    continue;
                }
                $pathMatched = true;
                if ($route['method'] !== $method) {
                    continue;
                }
                $params = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
                foreach ($route['middleware'] as $mw) {
                    self::call($mw, $params);
                }
                self::call($route['handler'], $params);
                return;
            }
            $this->fail($isApi, $pathMatched ? 405 : 404, $pathMatched ? 'Method not allowed' : 'Not found');
        } catch (Throwable $e) {
            error_log($e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
            $this->fail($isApi, 500, 'Something went wrong');
        }
    }

    private static function call(callable|array $target, array $params): mixed
    {
        if (is_array($target) && is_string($target[0])) {
            $ref = new \ReflectionMethod($target[0], $target[1]);
            $target = $ref->isStatic() ? [$target[0], $target[1]] : [new $target[0](), $target[1]];
        }
        return $target($params);
    }

    private function fail(bool $isApi, int $status, string $message): void
    {
        if ($isApi) {
            Response::error($message, $status);
        }
        http_response_code($status);
        echo $message;
    }
}
