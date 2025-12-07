<?php

declare(strict_types=1);

namespace App\Core;

class Router
{
    private array $routes = [
        'GET' => [],
        'POST' => [],
        'PUT' => [],
        'PATCH' => [],
        'DELETE' => [],
    ];

    public function get(string $path, callable|array $handler): void
    {
        $this->routes['GET'][$path] = $handler;
    }

    public function post(string $path, callable|array $handler): void
    {
        $this->routes['POST'][$path] = $handler;
    }

    public function put(string $path, callable|array $handler): void
    {
        $this->routes['PUT'][$path] = $handler;
    }

    public function delete(string $path, callable|array $handler): void
    {
        $this->routes['DELETE'][$path] = $handler;
    }

    public function dispatch(string $method, string $uri): void
    {
        $routes = $this->routes[$method] ?? [];

        // Basit path param desteği: /api/sites/{siteId}/pages
        foreach ($routes as $routePattern => $handler) {
            $pattern = preg_replace('#\{[a-zA-Z_][a-zA-Z0-9_]*\}#', '([a-zA-Z0-9\-_]+)', $routePattern);
            $pattern = '#^' . $pattern . '$#';

            if (preg_match($pattern, $uri, $matches)) {
                array_shift($matches); // full match

                $response = is_array($handler)
                    ? (new $handler[0]())->{$handler[1]}(...$matches)
                    : $handler(...$matches);

                if ($response instanceof Response) {
                    $response->send();
                } elseif (is_array($response)) {
                    Response::json($response)->send();
                } else {
                    echo (string) $response;
                }

                return;
            }
        }

        http_response_code(404);
        echo json_encode(['error' => 'Not Found']);
    }
}


