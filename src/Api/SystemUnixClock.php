<?php

declare(strict_types=1);

namespace BrifnetMpesa\Api;

final class SystemUnixClock implements UnixClock
{
    public function now(): int
    {
        return time();
    }
}
