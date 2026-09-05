<?php

declare(strict_types=1);

namespace BrifnetMpesa\Api;

final class SystemDateTimeProvider implements DateTimeProvider
{
    public function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable();
    }
}