<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2026, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Proxy\Generator;

use PhpParser\Modifiers;
use ReflectionMethod;

/**
 * Visibility of a generated class member.
 *
 * The backing value is the exact PHP keyword rendered in generated proxy code.
 */
enum Visibility: string
{
    case PUBLIC    = 'public';
    case PROTECTED = 'protected';
    case PRIVATE   = 'private';

    /**
     * Derives the visibility from a reflection method (boundary conversion
     * from PHP's own ReflectionMethod modifiers).
     */
    public static function fromReflectionMethod(ReflectionMethod $method): self
    {
        return match (true) {
            $method->isPrivate()   => self::PRIVATE,
            $method->isProtected() => self::PROTECTED,
            default                => self::PUBLIC,
        };
    }

    /**
     * Maps this visibility to the corresponding PhpParser Modifiers constant.
     */
    public function toAstModifier(): int
    {
        return match ($this) {
            self::PUBLIC    => Modifiers::PUBLIC,
            self::PROTECTED => Modifiers::PROTECTED,
            self::PRIVATE   => Modifiers::PRIVATE,
        };
    }
}
