<?php

declare(strict_types=1);

namespace BrifnetMpesa\Api;

interface AccessTokenProvider
{
    public function getAccessToken(): AccessTokenResult;
}
