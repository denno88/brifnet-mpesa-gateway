<?php

declare(strict_types=1);

namespace BrifnetMpesa\Tests;

use BrifnetMpesa\WordPress\WordPressHookRegistrar;
use PHPUnit\Framework\TestCase;

final class WordPressHookRegistrarTest extends TestCase
{
    public function testRegistrarIsCreated(): void
    {
        $registrar = new WordPressHookRegistrar();

        $this->assertInstanceOf(
            WordPressHookRegistrar::class,
            $registrar
        );
    }
}