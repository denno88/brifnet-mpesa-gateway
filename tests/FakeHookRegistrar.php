<?php

declare(strict_types=1);

namespace BrifnetMpesa\Tests;

use BrifnetMpesa\WordPress\HookRegistrar;

final class FakeHookRegistrar implements HookRegistrar
{
    public array $actions = [];

    public function addAction(
        string $hook,
        callable $callback
    ): void {
        $this->actions[$hook] = $callback;
    }
}
