<?php

declare(strict_types=1);

namespace BrifnetMpesa\Tests;

use BrifnetMpesa\Api\AccessTokenAuthenticator;
use BrifnetMpesa\Api\AccessTokenResult;

final class FakeAccessTokenAuthenticator implements AccessTokenAuthenticator
{
    public int $callCount = 0;

    public AccessTokenResult $result;

    public function authenticate(): AccessTokenResult
    {
        $this->callCount++;

        return $this->result;
    }
}
