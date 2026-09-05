<?php

declare(strict_types=1);

namespace BrifnetMpesa\Database;

interface Schema
{
    public function install(): void;
}