<?php

declare(strict_types=1);

namespace BrifnetMpesa\Api;

interface AccessTokenAuthenticator
{
    public function authenticate(): AccessTokenResult;
}
