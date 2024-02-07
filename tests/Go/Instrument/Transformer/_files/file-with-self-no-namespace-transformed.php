<?php
/** @noinspection PhpIllegalPsrClassPathInspection */
declare(strict_types=1);

class ClassWithSelfNoNamespace extends \Exception
{
    const CLASS_CONST = \ClassWithSelfNoNamespace::class;

    private static $foo = 42;

    private \ClassWithSelfNoNamespace $instance;

    public function acceptsAndReturnsSelf(\ClassWithSelfNoNamespace $instance): \ClassWithSelfNoNamespace
    {
        return $instance;
    }

    public function containsClosureWithSelf()
    {
        $func = function (\ClassWithSelfNoNamespace $instance): \ClassWithSelfNoNamespace {
            return $instance;
        };
        $func($this);
    }

    public function staticMethodCall()
    {
        return \ClassWithSelfNoNamespace::staticPropertyAccess();
    }

    public function classConstantFetch()
    {
        return \ClassWithSelfNoNamespace::class . \ClassWithSelfNoNamespace::CLASS_CONST;
    }

    public static function staticPropertyAccess()
    {
        return self::$foo;
    }

    public function newInstanceCreation()
    {
        return new \ClassWithSelfNoNamespace;
    }

    public function catchSection()
    {
        try {
            throw new \ClassWithSelfNoNamespace;
        } catch (\ClassWithSelfNoNamespace $exception) {
            // Nop
        }
    }

    public function instanceCheck()
    {
        if ($this instanceof \ClassWithSelfNoNamespace) {
            // Nop
        }
    }
}
