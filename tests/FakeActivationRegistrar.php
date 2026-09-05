<?php

declare(strict_types=1);

namespace BrifnetMpesa\Tests;

use BrifnetMpesa\WordPress\ActivationRegistrar;

final class FakeActivationRegistrar implements ActivationRegistrar
{
    public ?string $pluginFile = null;

    public array $callbacks = [];

    public function register(
        string $pluginFile,
        callable $callback,
    ): void {
        $this->pluginFile = $pluginFile;
        $this->callbacks[] = $callback;
    }

    public function callback(): void
    {
        foreach ($this->callbacks as $callback) {
            $callback();
        }
    }
}