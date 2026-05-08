<?php
declare(strict_types=1);

namespace App;

class Router {
    /** @var array<int,array{0:string,1:string,2:callable}> */
    private array $routes = [];

    public function add(string $method, string $pattern, callable $handler): void {
        $this->routes[] = [strtoupper($method), $pattern, $handler];
    }

    public function get(string $p, callable $h): void { $this->add('GET', $p, $h); }
    public function post(string $p, callable $h): void { $this->add('POST', $p, $h); }
    public function any(string $p, callable $h): void {
        $this->add('GET', $p, $h);
        $this->add('POST', $p, $h);
    }

    public function dispatch(string $method, string $uri): void {
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $path = '/' . trim($path, '/');
        if ($path === '') $path = '/';

        foreach ($this->routes as [$m, $pattern, $handler]) {
            if ($m !== $method) continue;
            $params = $this->match($pattern, $path);
            if ($params !== null) {
                $handler(...array_values($params));
                return;
            }
        }

        // 404
        http_response_code(404);
        echo "<!doctype html><meta charset=utf-8><title>404</title><h1>404 — Sayfa bulunamadı</h1><p><a href='/'>Ana sayfa</a></p>";
    }

    private function match(string $pattern, string $path): ?array {
        $pattern = '/' . trim($pattern, '/');
        if ($pattern === '') $pattern = '/';
        $regex = preg_replace('#\{([a-zA-Z_]\w*)\}#', '(?P<$1>[^/]+)', $pattern);
        $regex = '#^' . $regex . '$#u';
        if (!preg_match($regex, $path, $m)) return null;
        $out = [];
        foreach ($m as $k => $v) {
            if (!is_int($k)) $out[$k] = $v;
        }
        return $out;
    }
}
