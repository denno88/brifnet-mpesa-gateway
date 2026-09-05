<?php

declare(strict_types=1);

namespace BrifnetMpesa\WordPress;

final class WordPressActivationRegistrar implements ActivationRegistrar
{
    public function register(
        string $pluginFile,
        callable $callback,
    ): void {
        register_activation_hook(
            $pluginFile,
            $callback
        );
    }
}