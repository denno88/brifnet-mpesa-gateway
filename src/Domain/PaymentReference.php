<?php

declare(strict_types=1);

namespace BrifnetMpesa\Domain;

use InvalidArgumentException;

final readonly class PaymentReference
{
    public function __construct(
        public string $value,
    ) {
        if (trim($this->value) === '') {
            throw new InvalidArgumentException(
                'Payment reference cannot be empty.'
            );
        }
    }
}
