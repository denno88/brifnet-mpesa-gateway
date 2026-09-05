<?php

declare(strict_types=1);

namespace BrifnetMpesa\Tests;

use BrifnetMpesa\WordPress\TransientStore;

final class FakeTransientStore implements TransientStore
{
    public array $values = [];

    public array $expirations = [];

    public array $deletedKeys = [];

    public function get(string $key): mixed
    {
        return $this->values[$key] ?? false;
    }

    public function set(
        string $key,
        mixed $value,
        int $expiration,
    ): void {
        $this->values[$key] = $value;
        $this->expirations[$key] = $expiration;
    }

    public function delete(string $key): void
    {
        unset($this->values[$key]);
        $this->deletedKeys[] = $key;
    }
}
