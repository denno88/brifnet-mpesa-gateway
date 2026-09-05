<?php

declare(strict_types=1);

namespace BrifnetMpesa\Tests;

use BrifnetMpesa\Domain\PhoneNumber;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class PhoneNumberTest extends TestCase
{

    public function testValidKenyanPhoneNumberIsAccepted(): void
    {
        $phone = new PhoneNumber('0712345678');

        $this->assertSame('0712345678', $phone->value);
    }

    public function testInvalidPhoneNumberIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Phone number must be a valid Kenyan mobile number.'
        );

        new PhoneNumber('1234567890');
    }
}
