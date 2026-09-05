<?php

declare(strict_types=1);

namespace BrifnetMpesa\Tests;

use BrifnetMpesa\Database\Schema;

final class FakeSchema implements Schema
{
    public int $installCount = 0;

    public function install(): void
    {
        $this->installCount++;
    }
}