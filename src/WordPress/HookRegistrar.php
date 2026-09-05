<?php

declare(strict_types=1);

namespace BrifnetMpesa\WordPress;

interface HookRegistrar
{
    public function addAction(
        string $hook,
        callable $callback
    ): void;
}