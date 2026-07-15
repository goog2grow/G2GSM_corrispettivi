<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Router HTTP minimale, senza dipendenze esterne.
 * Supporta segmenti dinamici tipo /brand/{id}/toggle.
 */
final class Router
{
    /** @var array<int, array{0:string,1:string,2:callable}> */
    private array $routes = [];

    public function get(string $path, callable $handler): void
    {
        $this->routes[] = ['GET', $path, $handler];
    }

    public function post(string $path, callable $handler): void
    {
        $this->routes[] = ['POST', $path, $handler];
    }

    public function dispatch(string $method, string $requestUri): void
    {
        $path = (string) (parse_url($requestUri, PHP_URL_PATH) ?? '/');
        $path = rtrim($path, '/');
        if ($path === '') {
            $path = '/';
        }

        foreach ($this->routes as [$routeMethod, $routePath, $handler]) {
            if ($routeMethod !== $method) {
                continue;
            }

            $pattern = $this->compile($routePath);
            if (preg_match($pattern, $path, $matches) === 1) {
                $params = array_filter($matches, static fn ($key) => is_string($key), ARRAY_FILTER_USE_KEY);
                $handler(...array_values($params));
                return;
            }
        }

        http_response_code(404);
        echo '404 - Pagina non trovata';
    }

    private function compile(string $routePath): string
    {
        $escaped = preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', '(?P<$1>[^/]+)', $routePath);

        return '#^' . $escaped . '$#';
    }
}
