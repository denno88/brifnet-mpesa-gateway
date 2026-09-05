<?php

declare(strict_types=1);

namespace BrifnetMpesa\Api;

final class SystemClock implements Clock
{
    public function timestamp(): string
    {
        return date('YmdHis');
    }
}
