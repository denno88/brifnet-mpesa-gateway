<?php

declare(strict_types=1);

namespace BrifnetMpesa\Tests;

use BrifnetMpesa\Api\AccessTokenProvider;
use BrifnetMpesa\Api\AccessTokenResult;

final class FakeAccessTokenProvider implements AccessTokenProvider
{
    public int $callCount = 0;

    public AccessTokenResult $result;

    public function getAccessToken(): AccessTokenResult
    {
        $this->callCount++;

        return $this->result;
    }
}
