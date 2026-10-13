<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Tiny method + path router. Route parameters written as {name} match
 * digits only, which is all this application needs (record IDs).
 */
final class Router
{
    /** @var list<array{method: string, regex: string, handler: array{class-string, string}}> */
    private array $routes = [];

    /** @param array{class-string, string} $handler */
    public function get(string $path, array $handler): void
    {
        $this->add('GET', $path, $handler);
    }

    /** @param array{class-string, string} $handler */
    public function post(string $path, array $handler): void
    {
        $this->add('POST', $path, $handler);
    }

    public function dispatch(string $method, string $path): void
    {
        $allowed = [];

        foreach ($this->routes as $route) {
            if (preg_match($route['regex'], $path, $matches) !== 1) {
                continue;
            }

            $routeMethod = $route['method'];
            if ($routeMethod !== $method && !($routeMethod === 'GET' && $method === 'HEAD')) {
                $allowed[] = $routeMethod;
                continue;
            }

            $params = [];
            foreach ($matches as $key => $value) {
                if (is_string($key)) {
                    $params[$key] = $value;
                }
            }

            [$class, $action] = $route['handler'];
            (new $class())->{$action}(...$params);

            return;
        }

        if ($allowed !== []) {
            $allowed = array_values(array_unique($allowed));
            if (in_array('GET', $allowed, true)) {
                $allowed[] = 'HEAD';
            }
            throw new HttpException(405, '', ['Allow' => implode(', ', $allowed)]);
        }

        throw new HttpException(404);
    }

    /** @param array{class-string, string} $handler */
    private function add(string $method, string $path, array $handler): void
    {
        $pattern = preg_replace('#\{([a-z_]+)\}#', '(?P<$1>[0-9]{1,10})', $path);
        $this->routes[] = [
            'method'  => $method,
            'regex'   => '#^' . $pattern . '$#',
            'handler' => $handler,
        ];
    }
}
