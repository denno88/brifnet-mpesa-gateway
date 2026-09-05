<?php

declare(strict_types=1);

namespace BrifnetMpesa\WordPress;

interface RestRegistrar
{
    public function registerRoute(
        string $namespace,
        string $route,
        array $args,
    ): void;
}