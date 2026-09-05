<?php

declare(strict_types=1);

namespace BrifnetMpesa\Domain;

use InvalidArgumentException;

final readonly class PhoneNumber
{
    public function __construct(
        public string $value,
    ) {
        if (!preg_match('/^07\d{8}$/', $this->value)) {
            throw new InvalidArgumentException(
                'Phone number must be a valid Kenyan mobile number.'
            );
        }
    }
}
