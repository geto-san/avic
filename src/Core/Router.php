<?php
declare(strict_types=1);

namespace Avic\Core;

final class Router
{
    /** @var list<array{string,string,array}> */
    private array $routes = [];

    public function get(string $pattern, array $handler): void
    {
        $this->routes[] = ['GET', $pattern, $handler];
    }

    public function post(string $pattern, array $handler): void
    {
        $this->routes[] = ['POST', $pattern, $handler];
    }

    public function dispatch(Request $req): void
    {
        foreach ($this->routes as [$method, $pattern, $handler]) {
            if ($method !== $req->method) {
                continue;
            }
            $regex = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>\d+)', $pattern) . '$#';
            if (!preg_match($regex, $req->path, $m)) {
                continue;
            }
            if ($method === 'POST' && !Csrf::valid($req->post['_csrf'] ?? null)) {
                throw new HttpException(419, 'Your session expired. Reload the page and try again.');
            }
            $params = [];
            foreach ($m as $k => $v) {
                if (is_string($k)) {
                    $params[$k] = (int)$v;
                }
            }
            $handler($req, $params);
            return;
        }
        throw new HttpException(404);
    }
}
