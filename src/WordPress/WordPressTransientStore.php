<?php

declare(strict_types=1);

namespace BrifnetMpesa\WordPress;

final class WordPressTransientStore implements TransientStore
{
    public function get(string $key): mixed
    {
        return get_transient($key);
    }

    public function set(
        string $key,
        mixed $value,
        int $expiration,
    ): void {
        set_transient(
            $key,
            $value,
            $expiration
        );
    }

    public function delete(string $key): void
    {
        delete_transient($key);
    }
}
