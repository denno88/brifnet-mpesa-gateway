<?php

declare(strict_types=1);

namespace BrifnetMpesa\WordPress;

interface ActivationRegistrar
{
    public function register(
        string $pluginFile,
        callable $callback,
    ): void;
}