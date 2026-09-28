<?php

declare(strict_types=1);

namespace BrifnetMpesa\Application;

interface PaymentReferenceGenerator
{
    public function generate(): string;
}