<?php

declare(strict_types=1);

namespace BrifnetMpesa\Api;

interface DateTimeProvider
{
    public function now(): \DateTimeImmutable;
}