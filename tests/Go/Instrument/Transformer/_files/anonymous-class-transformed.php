<?php
/**
 * Parser Reflection API
 *
 * @copyright Copyright 2016, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */
declare(strict_types=1);

namespace Go\ParserReflection\Stub;

class InAnonymousClass
{
    public function respond()
    {
        new class {
            public const FOO = 'foo';

            public function run()
            {
                return self::FOO;
            }
        };
    }
}
