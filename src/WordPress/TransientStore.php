<?php

declare(strict_types=1);

namespace BrifnetMpesa\WordPress;

interface TransientStore
{
    public function get(string $key): mixed;

    public function set(
        string $key,
        mixed $value,
        int $expiration,
    ): void;

    public function delete(string $key): void;
}