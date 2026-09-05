<?php

declare(strict_types=1);

namespace BrifnetMpesa\Api;

interface UnixClock
{
    public function now(): int;
}
