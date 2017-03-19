<?php
declare(strict_types = 1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2014, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Demo\Example;

use Demo\Annotation\Deprecated as deprecated; // <== We import specific system annotation

/**
 * In this class we use system functions that will be intercepted by aspect
 */
class ErrorDemo
{

    /**
     * Some old method that is used by system in production, but shouldn't be used for the new code
     *
     * @deprecated
     */
    public function oldMethod()
    {
        echo "Hello, I'm old method", PHP_EOL;
    }

    /**
     * Method that is very tricky and should generate a notice
     */
    public function notSoGoodMethod()
    {
        $value = round(microtime(true)) % 3; // Sometimes this equal to 0

        return rand(1, 10) / $value;
    }
}
