<?php

declare(strict_types=1);

namespace Go\Tests\TestProject\Application;

/**
 * Pure unit enum used by EnumWeavingTest to verify basic enum interception.
 *
 * This enum has no backing type, a constant, an instance method, and a static method
 * — covering the full surface tested by the functional test suite.
 */
enum SimpleEnum
{
    case North;
    case South;
    case East;
    case West;

    public const CATEGORY = 'direction';

    public function doSomething(): string
    {
        return $this->name;
    }

    public static function doSomethingStatic(): string
    {
        return self::CATEGORY;
    }
}
