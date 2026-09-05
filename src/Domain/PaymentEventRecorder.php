<?php

declare(strict_types=1);

namespace BrifnetMpesa\Domain;

interface PaymentEventRecorder
{
    public function record(PaymentCompleted $event): void;
}