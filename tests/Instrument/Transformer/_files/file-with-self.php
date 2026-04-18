<?php
declare(strict_types=1);

namespace Test\ns1
{
    class ClassWithSelf extends \Exception
    {
        const CLASS_CONST = self::class;

        private static $foo = 42;

        private self $instance;

        public function acceptsAndReturnsSelf(self $instance): self
        {
            return $instance;
        }

        public function containsClosureWithSelf()
        {
            $func = function (self $instance): self {
                return $instance;
            };
            $func($this);
        }

        public function staticMethodCall()
        {
            return self::staticPropertyAccess();
        }

        public function classConstantFetch()
        {
            return self::class . self::CLASS_CONST;
        }

        public static function staticPropertyAccess()
        {
            return self::$foo;
        }

        public function newInstanceCreation()
        {
            return new self;
        }

        public function catchSection()
        {
            try {
                throw new self;
            } catch (self $exception) {
                // Nop
            }
        }

        public function instanceCheck()
        {
            if ($this instanceof self) {
                // Nop
            }
        }
    }
}
