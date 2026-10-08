<?php
declare(strict_types=1);
namespace Test\ns1 {
    use Go\Stubs\Collision\Interceptor as Level;

    class FirstBlockClass
    {
    }
}

namespace Test\ns1 {
    use Go\Stubs\Collision\The as Level;

    class SecondBlockClass
    {
        public function log(string $level = Level::LEVEL): string
        {
            return $level;
        }
    }
}
