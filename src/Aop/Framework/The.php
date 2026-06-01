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

namespace Go\Aop\Framework;

use Go\Aop\Aspect;
use Go\Core\AspectKernel;

/**
 * Accessor for aspect instances from generated proxy code.
 */
final class The
{
    /**
     * @template T of Aspect
     * @param class-string<T> $aspectClass
     * @return T
     */
    public static function aspect(string $aspectClass): Aspect
    {
        return AspectKernel::getInstance()->getContainer()->getService($aspectClass);
    }
}
