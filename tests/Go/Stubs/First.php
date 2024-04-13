<?php

declare(strict_types = 1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2018, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Stubs;

#[StubAttribute(First::class)]
class First
{

    private int $private = T_PRIVATE;
    protected int $protected = T_PROTECTED;
    public int $public = T_PUBLIC;

    #[StubAttribute(First::class)]
    public string $publicWithAttribute = 'attribute';

    private static int $staticPrivate = T_PRIVATE;
    protected static int $staticProtected = T_PROTECTED;
    protected static int $staticPublic = T_PUBLIC;

    // Dynamic methods that access $this-> properties
    private function privateMethod(): int
    {
        return $this->private;
    }

    protected function protectedMethod(): int
    {
        return $this->protected;
    }

    public function publicMethod(): int
    {
        return $this->public;
    }

    public final function publicFinalMethod(): void
    {
        // nothing here
    }

    protected final function protectedFinalMethod(): void
    {
        // nothing here
    }

    #[StubAttribute(First::class)]
    public function publicMethodWithAttribute(): string
    {
        return $this->publicWithAttribute;
    }

    // Static methods that access self:: properties
    private static function staticSelfPrivate(): int
    {
        return self::$staticPrivate;
    }

    protected static function staticSelfProtected(): int
    {
        return self::$staticProtected;
    }

    public static function staticSelfPublic(): int
    {
        return self::$staticPublic;
    }

    public static function staticSelfPublicAccessPrivate(): int
    {
        return self::$staticPrivate;
    }

    protected static function staticLsbProtected(): string
    {
        return get_called_class();
    }

    public static function staticLsbPublic(): string
    {
        return get_called_class();
    }

    // Pass by reference
    public function passByReference(&$valueByReference)
    {
        $valueByReference = null;
        return $valueByReference;
    }

    // Pass by reference
    public static function staticPassByReference(&$valueByReference)
    {
        $valueByReference = null;
        return $valueByReference;
    }

    // Recursion test
    public function recursion(int $value, int $level = 0): int
    {
        if ($level > 0) {
            $value += $this->recursion($value, $level-1);
        }

        return $value;
    }

    // Recursion test
    public static function staticLsbRecursion(int $value, int $level = 0): int
    {
        if ($level > 0) {
            $value += static::staticLsbRecursion($value, $level-1);
        }

        return $value;
    }

    /**
     * Method for checking invocation with any number of arguments
     *
     * NB: Real proxy use the method definition to prepare invocation proxy, so variable number of arguments
     * will not work at all!
     */
    public function variableArgsTest(): string
    {
        return implode('', func_get_args());
    }

    /**
     * Method for checking variadic arguments
     */
    public function variadicArgsTest(...$args): string
    {
        return implode('', $args);
    }

    /**
     * Method for checking invocation with any number of arguments
     *
     * NB: Real proxy use the method definition to prepare invocation proxy, so variable number of arguments
     * will not work at all!
     */
    public static function staticVariableArgsTest(): string
    {
        return implode('', func_get_args());
    }

    /**
     * Method for checking static variadic arguments
     */
    public static function staticVariadicArgsTest(...$args): string
    {
        return implode('', $args);
    }
}
