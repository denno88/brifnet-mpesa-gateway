<?php

declare(strict_types=1);

namespace BrifnetMpesa\Tests;

use BrifnetMpesa\WordPress\RestRegistrar;

final class FakeRestRegistrar implements RestRegistrar
{
    public ?string $namespace = null;
    public ?string $route = null;
    public array $args = [];
    public array $routes = [];

    public function registerRoute(
        string $namespace,
        string $route,
        array $args,
    ): void {
        $this->namespace = $namespace;
        $this->route = $route;
        $this->args = $args;

        $this->routes[] = [
            'namespace' => $namespace,
            'route' => $route,
            'args' => $args,
        ];
    }
}