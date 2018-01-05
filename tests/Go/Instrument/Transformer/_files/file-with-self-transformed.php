<?php
namespace Test\ns1
{
    class ClassWithSelf extends \Exception
    {
        const CLASS_CONST = \Test\ns1\ClassWithSelf::class;

        private static $foo = 42;

        public function acceptsAndReturnsSelf(\Test\ns1\ClassWithSelf $instance): \Test\ns1\ClassWithSelf
        {
            return $instance;
        }

        public function containsClosureWithSelf()
        {
            $func = function (\Test\ns1\ClassWithSelf $instance): \Test\ns1\ClassWithSelf {
                return $instance;
            };
            $func($this);
        }

        public function staticMethodCall()
        {
            return \Test\ns1\ClassWithSelf::staticPropertyAccess();
        }

        public function classConstantFetch()
        {
            return \Test\ns1\ClassWithSelf::class . \Test\ns1\ClassWithSelf::CLASS_CONST;
        }

        public static function staticPropertyAccess()
        {
            return self::$foo;
        }

        public function newInstanceCreation()
        {
            return new \Test\ns1\ClassWithSelf;
        }

        public function catchSection()
        {
            try {
                throw new \Test\ns1\ClassWithSelf;
            } catch (\Test\ns1\ClassWithSelf $exception) {
                // Nop
            }
        }

        public function instanceCheck()
        {
            if ($this instanceof \Test\ns1\ClassWithSelf) {
                // Nop
            }
        }
    }
}
