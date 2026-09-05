<?php

declare(strict_types=1);

namespace BrifnetMpesa\WordPress;

final readonly class RestResponse
{
    public function __construct(
        public int $statusCode,
        public array $body,
    ) {
    }
}