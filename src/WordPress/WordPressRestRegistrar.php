<?php

declare(strict_types=1);

namespace BrifnetMpesa\WordPress;

final class WordPressRestRegistrar implements RestRegistrar
{
    public function registerRoute(
        string $namespace,
        string $route,
        array $args,
    ): void {
        register_rest_route(
            $namespace,
            $route,
            $args
        );
    }
}