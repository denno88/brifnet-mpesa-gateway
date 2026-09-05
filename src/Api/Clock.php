<?php

declare(strict_types=1);

namespace BrifnetMpesa\Api;

interface Clock
{
    public function timestamp(): string;
}
