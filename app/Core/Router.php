<?php

namespace App\Core;

class Router
{
    protected array $routes = [
        'GET' => [],
        'POST' => [],
    ];

    public function get(string $uri, callable|array $action): void
    {
        $this->addRoute('GET', $uri, $action);
    }

    public function post(string $uri, callable|array $action): void
    {
        $this->addRoute('POST', $uri, $action);
    }

    protected function addRoute(string $method, string $uri, callable|array $action): void
    {
        $uri = '/' . trim($uri, '/');
        $this->routes[$method][$uri] = $action;
    }

    public function dispatch(Request $request): Response
    {
        $method = $request->method();
        $uri = $request->uri();

        if (isset($this->routes[$method])) {
            foreach ($this->routes[$method] as $route => $action) {
                if ($params = $this->match($route, $uri)) {
                    return $this->callAction($action, $params === true ? [] : $params, $request);
                }
            }
        }

        // Matched on another registered method? That's a 405, not a 404.
        foreach ($this->routes as $otherMethod => $routes) {
            if ($otherMethod === $method) {
                continue;
            }
            foreach ($routes as $route => $action) {
                if ($this->match($route, $uri)) {
                    return $this->methodNotAllowed();
                }
            }
        }

        return $this->notFound();
    }

    /** Returns an assoc array of matched params, true for a param-less match, or false. */
    protected function match(string $route, string $uri): array|bool
    {
        $paramNames = [];

        $pattern = preg_replace_callback(
            '/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/',
            function ($m) use (&$paramNames) {
                $paramNames[] = $m[1];
                return '([^/]+)';
            },
            $route
        );

        if (!preg_match('#^' . $pattern . '$#', $uri, $matches)) {
            return false;
        }

        array_shift($matches);

        return $paramNames === [] ? true : array_combine($paramNames, array_map('urldecode', $matches));
    }

    /**
     * Route params are spread as named arguments, so a controller method's
     * parameter names must match the route's {placeholders} exactly, e.g.
     * route '/users/{id}' => method show(Request $request, string $id).
     */
    protected function callAction(callable|array $action, array $params, Request $request): Response
    {
        if (is_array($action)) {
            [$class, $methodName] = $action;
            $result = (new $class())->{$methodName}($request, ...$params);
        } else {
            $result = $action($request, ...$params);
        }

        return $result instanceof Response ? $result : new Response((string) $result);
    }

    protected function notFound(): Response
    {
        return new Response('<h1>404</h1><p>Page not found.</p>', 404);
    }

    protected function methodNotAllowed(): Response
    {
        return new Response('<h1>405</h1><p>Method not allowed.</p>', 405);
    }
}
