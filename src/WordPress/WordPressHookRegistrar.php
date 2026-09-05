<?php

declare(strict_types=1);

namespace BrifnetMpesa\WordPress;

final class WordPressHookRegistrar implements HookRegistrar
{
    public function addAction(
        string $hook,
        callable $callback
    ): void {
        add_action($hook, $callback);
    }
}